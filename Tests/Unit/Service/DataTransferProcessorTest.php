<?php

declare(strict_types=1);

/*
 * This file is part of the package cpsit/import-export-core.
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or (at
 * your option) any later version.
 *
 * For the full license information, please read the LICENSE file that was
 * distributed with this source code.
 */

namespace CPSIT\ImportExportCore\Tests\Unit\Service;

use CPSIT\ImportExportCore\Condition\ConditionEvaluatorInterface;
use CPSIT\ImportExportCore\Domain\Model\Dto\TaskDemand;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Event\BatchProcessed;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Processor\SetValuesPreProcessor;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Service\TransferProcessorInterface;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\AppendingInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\EmptyRunTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\LoggingPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\LogInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkingPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ObjectConverter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RecordingFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ReplacingFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ResultFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ToObjectConverter;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

require_once __DIR__ . '/Fixtures/Components.php';

class DataTransferProcessorTest extends TestCase
{
    protected DataTransferProcessor $subject;

    protected function setUp(): void
    {
        $this->subject = new DataTransferProcessor();
    }

    /**
     * @param iterable<mixed> $records
     */
    private function createTask(iterable $records, ?InMemoryTarget $target = null, string $identifier = 'task'): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier($identifier);
        $task->setSource(new ArraySource($records));
        $task->setTarget($target ?? new InMemoryTarget());

        return $task;
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(TransferProcessorInterface::class, $this->subject);
    }

    public function testThrowsWithoutDataSource(): void
    {
        $task = new TransferTask();
        $task->setIdentifier('noSource');
        $task->setTarget(new InMemoryTarget());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794160001);

        $this->subject->process($task);
    }

    public function testThrowsWithoutDataTarget(): void
    {
        $task = new TransferTask();
        $task->setIdentifier('noTarget');
        $task->setSource(new ArraySource([]));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794160002);

        $this->subject->process($task);
    }

    public function testPersistsAllRecordsAndAddsThemToResult(): void
    {
        $target = new InMemoryTarget();
        $records = [['id' => 1], ['id' => 2]];

        $result = $this->subject->process($this->createTask($records, $target));

        self::assertSame($records, $target->persisted);
        self::assertSame($records, $result->toArray());
        self::assertCount(2, $result);
    }

    public function testPassesSourceConfigurationToSource(): void
    {
        $task = $this->createTask([]);
        $source = $task->getSource();
        self::assertInstanceOf(ArraySource::class, $source);
        $source->setConfiguration(['table' => 'foo']);

        $this->subject->process($task);

        self::assertSame(['table' => 'foo'], $source->lastConfiguration);
    }

    public function testPassesResultAndTargetConfigurationToPersistAll(): void
    {
        $target = new InMemoryTarget();
        $target->setConfiguration(['table' => 'bar']);

        $result = $this->subject->process($this->createTask([['id' => 1]], $target));

        self::assertCount(1, $target->persistAllCalls);
        self::assertSame($result, $target->persistAllCalls[0]['result']);
        self::assertSame(['table' => 'bar'], $target->persistAllCalls[0]['configuration']);
    }

    public function testDoesNotCallPersistAllWithoutRecords(): void
    {
        $target = new InMemoryTarget();

        $this->subject->process($this->createTask([], $target));

        self::assertSame([], $target->persistAllCalls);
    }

    public function testCallsPersistEmptyRunOfAwareTargetWithoutRecords(): void
    {
        $target = new EmptyRunTarget();
        $target->setConfiguration(['file' => 'a.csv']);
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource(new ArraySource([]));
        $task->setTarget($target);

        $result = $this->subject->process($task);

        self::assertSame(['persistEmptyRun'], $target->calls);
        self::assertSame($result, $target->emptyRunResult);
        self::assertSame(['file' => 'a.csv'], $target->emptyRunConfiguration);
    }

    public function testCallsPersistAllOfAwareTargetWithRecords(): void
    {
        $target = new EmptyRunTarget();
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource(new ArraySource([['id' => 1]]));
        $task->setTarget($target);

        $this->subject->process($task);

        self::assertSame(['persist', 'persistAll'], $target->calls);
    }

    public function testRunsComponentsInOrder(): void
    {
        $log = [];
        $task = $this->createTask([['id' => 1], ['id' => 2]]);
        $task->setInitializers([new LogInitializer($log)]);
        $preProcessor = new FieldPreProcessor($log);
        $preProcessor->setConfiguration(['field' => 'title', 'value' => 'foo']);
        $task->setPreProcessors([$preProcessor]);
        $task->setConverters([new ToObjectConverter($log)]);
        $task->setPostProcessors([new MarkPostProcessor($log)]);
        $task->setFinishers([new ResultFinisher($log)]);

        $this->subject->process($task);

        self::assertSame(
            [
                'initializer',
                'preProcessor:1', 'converter:1', 'postProcessor:1',
                'preProcessor:2', 'converter:2', 'postProcessor:2',
                'finisher',
            ],
            $log
        );
    }

    public function testPersistsConvertedAndPostProcessedRecords(): void
    {
        $target = new InMemoryTarget();
        $task = $this->createTask([['id' => 1]], $target);
        $preProcessor = new FieldPreProcessor();
        $preProcessor->setConfiguration(['field' => 'title', 'value' => 'foo']);
        $task->setPreProcessors([$preProcessor]);
        $task->setConverters([new ToObjectConverter()]);
        $task->setPostProcessors([new MarkPostProcessor()]);

        $result = $this->subject->process($task);

        $expected = (object)['id' => 1, 'title' => 'foo', 'postProcessed' => true];
        self::assertEquals([$expected], $target->persisted);
        self::assertEquals([$expected], $result->toArray());
    }

    public function testRunsDeprecatedComponentsThroughAdapters(): void
    {
        $log = [];
        $target = new InMemoryTarget();
        $task = $this->createTask([['id' => 1]], $target);
        $task->setInitializers([new AppendingInitializer($log)]);
        $preProcessor = new SetFieldPreProcessor($log);
        $preProcessor->setConfiguration(['field' => 'title', 'value' => 'foo']);
        $task->setPreProcessors([$preProcessor]);
        $task->setConverters([new ObjectConverter($log)]);
        $task->setPostProcessors([new MarkingPostProcessor($log)]);
        $finisher = new RecordingFinisher($log);
        $task->setFinishers([$finisher]);

        $result = $this->subject->process($task);

        // initializers cannot add records any more; finishers receive no records
        self::assertSame(
            ['initializer', 'preProcessor:1', 'converter:1', 'postProcessor:1', 'finisher'],
            $log
        );
        self::assertEquals([(object)['id' => 1, 'title' => 'foo', 'postProcessed' => true]], $target->persisted);
        self::assertSame([], $finisher->records);
        self::assertSame($result, $finisher->result);
    }

    public function testFinishersReceiveResult(): void
    {
        $task = $this->createTask([['id' => 1]]);
        $finisher = new ResultFinisher();
        $task->setFinishers([$finisher]);

        $result = $this->subject->process($task);

        self::assertSame($result, $finisher->result);
    }

    public function testThrowsForNonArrayRecordsWithComponents(): void
    {
        $task = $this->createTask([(object)['id' => 1]]);
        $task->setConverters([new ToObjectConverter()]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794260001);

        $this->subject->process($task);
    }

    public function testPersistsNonArrayRecordsWithoutComponents(): void
    {
        $target = new InMemoryTarget();
        $record = (object)['id' => 1];

        $this->subject->process($this->createTask([$record], $target));

        self::assertSame([$record], $target->persisted);
    }

    public function testThrowsIfConverterOutputCannotBeChained(): void
    {
        $task = $this->createTask([['id' => 1]]);
        $task->setConverters([new ToObjectConverter(), new ToObjectConverter()]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794260002);

        $this->subject->process($task);
    }

    public function testRunsFinishersWithoutRecords(): void
    {
        $task = $this->createTask([]);
        $finisher = new RecordingFinisher();
        $task->setFinishers([$finisher]);

        $this->subject->process($task);

        self::assertSame(1, $finisher->calls);
        self::assertSame([], $finisher->records);
    }

    public function testFinisherCannotReplaceResult(): void
    {
        $task = $this->createTask([['id' => 1]]);
        $task->setFinishers([new ReplacingFinisher()]);

        $result = $this->subject->process($task);

        self::assertInstanceOf(ResultInterface::class, $result);
        self::assertSame([['id' => 1]], $result->toArray());
    }

    public function testSkipsDisabledComponents(): void
    {
        $task = $this->createTask([['id' => 1]]);
        $components = [
            new AppendingInitializer(),
            new SetFieldPreProcessor(),
            new ObjectConverter(),
            new MarkingPostProcessor(),
            new RecordingFinisher(),
        ];
        foreach ($components as $component) {
            $component->disabled = true;
        }
        [$initializer, $preProcessor, $converter, $postProcessor, $finisher] = $components;
        $task->setInitializers([$initializer]);
        $task->setPreProcessors([$preProcessor]);
        $task->setConverters([$converter]);
        $task->setPostProcessors([$postProcessor]);
        $task->setFinishers([$finisher]);

        $result = $this->subject->process($task);

        foreach ($components as $component) {
            self::assertSame(0, $component->calls, $component::class);
        }
        self::assertSame([['id' => 1]], $result->toArray());
    }

    public function testEvaluatesDisableOptionOfConfigurableComponents(): void
    {
        $preProcessor = new SetValuesPreProcessor();
        $preProcessor->setConfiguration([
            'values' => ['type' => 'event'],
            'disable' => ['expression' => "record['type'] == 'news'"],
        ]);
        $task = $this->createTask([['id' => 1, 'type' => 'news'], ['id' => 2, 'type' => 'page']]);
        $task->setPreProcessors([$preProcessor]);

        $result = $this->subject->process($task);

        self::assertSame([['id' => 1, 'type' => 'news'], ['id' => 2, 'type' => 'event']], $result->toArray());
    }

    public function testSkipsConfigurableComponentsDisabledByMessage(): void
    {
        $preProcessor = new SetValuesPreProcessor();
        $preProcessor->setConfiguration([
            'values' => ['type' => 'event'],
            'disable' => ['if' => ['result' => ['hasMessage' => '1794000001']]],
        ]);
        $task = $this->createTask([['id' => 1]]);
        $task->setPreProcessors([$preProcessor]);
        $result = new TaskResult();
        $result->addMessages([new Message('message', 'title', Message::SEVERITY_OK, 1794000001)]);

        $this->subject->process($task, $result);

        self::assertSame([['id' => 1]], $result->toArray());
    }

    public function testUsesGivenConditionEvaluator(): void
    {
        $preProcessor = new SetValuesPreProcessor();
        $preProcessor->setConfiguration(['values' => ['type' => 'event'], 'disable' => 'custom']);
        $task = $this->createTask([['id' => 1]]);
        $task->setPreProcessors([$preProcessor]);
        $conditionEvaluator = $this->createMock(ConditionEvaluatorInterface::class);
        $conditionEvaluator->expects(self::once())->method('isMet')
            ->with('custom', ['id' => 1], self::isInstanceOf(ResultInterface::class))
            ->willReturn(true);

        $result = (new DataTransferProcessor(conditionEvaluator: $conditionEvaluator))->process($task);

        self::assertSame([['id' => 1]], $result->toArray());
    }

    public function testDoesNotEvaluateConditionWithoutDisableOption(): void
    {
        $preProcessor = new SetValuesPreProcessor();
        $preProcessor->setConfiguration(['values' => ['type' => 'event']]);
        $task = $this->createTask([['id' => 1]]);
        $task->setPreProcessors([$preProcessor]);
        $conditionEvaluator = $this->createMock(ConditionEvaluatorInterface::class);
        $conditionEvaluator->expects(self::never())->method('isMet');

        $result = (new DataTransferProcessor(conditionEvaluator: $conditionEvaluator))->process($task);

        self::assertSame([['id' => 1, 'type' => 'event']], $result->toArray());
    }

    public function testSkipsRecordsForWhichTargetIsDisabled(): void
    {
        $target = new InMemoryTarget();
        $target->disabledForIds = [2];

        $result = $this->subject->process($this->createTask([['id' => 1], ['id' => 2], ['id' => 3]], $target));

        self::assertSame([['id' => 1], ['id' => 3]], $target->persisted);
        self::assertSame([['id' => 1], ['id' => 3]], $result->toArray());
    }

    public function testGathersMessagesOfLoggingComponents(): void
    {
        $task = $this->createTask([['id' => 1], ['id' => 2]]);
        $task->setPreProcessors([new LoggingPreProcessor()]);

        $result = $this->subject->process($task);

        $descriptions = array_map(
            static fn (MessageInterface $message): string => $message->getMessage(),
            $result->getMessages()
        );
        self::assertSame(['Record 1', 'Record 2'], $descriptions);
    }

    public function testGathersMessagesOfSourceAndTarget(): void
    {
        $source = new class() implements DataSourceInterface, LoggingInterface {
            use LoggingTrait;

            public function getRecords(array $configuration): iterable
            {
                yield ['id' => 1];
                $this->logMessage('Source', 'Skipped row');
                yield ['id' => 2];
                $this->logMessage('Source', 'At the end');
            }
        };
        $target = new class() implements DataTargetInterface, LoggingInterface {
            use LoggingTrait;

            public function persist(array|object $object, ?array $configuration = null): bool
            {
                $this->logMessage('Target', 'Written');

                return true;
            }

            public function persistAll(mixed $result = null, ?array $configuration = null): void
            {
            }
        };
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource($source);
        $task->setTarget($target);
        $batches = [];
        $dispatcher = new class($batches) implements EventDispatcherInterface {
            /**
             * @param list<int> $batches
             */
            public function __construct(private array &$batches)
            {
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof BatchProcessed) {
                    $this->batches[] = \count($event->result->getMessages());
                }

                return $event;
            }
        };

        $result = (new DataTransferProcessor(eventDispatcher: $dispatcher, batchSize: 1))->process($task);

        $descriptions = array_map(
            static fn (MessageInterface $message): string => $message->getMessage(),
            $result->getMessages()
        );
        self::assertSame(['Written', 'Skipped row', 'Written', 'At the end'], $descriptions);
        self::assertSame([1, 3], $batches);
    }

    public function testAcceptsRecordsFromGenerator(): void
    {
        $target = new InMemoryTarget();
        $generator = (static function (): \Generator {
            yield 'a' => ['id' => 1];
            yield 'b' => ['id' => 2];
        })();

        $this->subject->process($this->createTask($generator, $target));

        self::assertSame([['id' => 1], ['id' => 2]], $target->persisted);
    }

    public function testAddsToGivenResult(): void
    {
        $result = new TaskResult();
        $result->add('existing');

        $returned = $this->subject->process($this->createTask([['id' => 1]]), $result);

        self::assertSame($result, $returned);
        self::assertSame(['existing', ['id' => 1]], $result->toArray());
    }

    public function testProcessesTaskWithoutIdentifier(): void
    {
        $target = new InMemoryTarget();
        $task = new TransferTask();
        $task->setSource(new ArraySource([['id' => 1]]));
        $task->setTarget($target);

        $this->subject->process($task);

        self::assertSame([['id' => 1]], $target->persisted);
    }

    public function testBuildQueueReadsAllSourcesInAdvance(): void
    {
        $first = $this->createTask([['id' => 1]], null, 'first');
        $second = $this->createTask([['id' => 2]], null, 'second');
        $demand = new TaskDemand();
        $demand->setTasks([$first, $second]);

        $this->subject->buildQueue($demand);

        self::assertSame(['first' => [['id' => 1]], 'second' => [['id' => 2]]], $this->subject->getQueue());
    }

    public function testProcessUsesQueuedRecordsAndRemovesThemFromQueue(): void
    {
        $target = new InMemoryTarget();
        $task = $this->createTask([['id' => 'source']], $target, 'queued');
        $source = $task->getSource();
        self::assertInstanceOf(ArraySource::class, $source);
        $this->subject->withQueue(['queued' => [['id' => 'queue']]]);

        $this->subject->process($task);

        self::assertSame([['id' => 'queue']], $target->persisted);
        self::assertSame(0, $source->reads);
        self::assertSame([], $this->subject->getQueue());
    }
}
