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

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Persistence\CompletableSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Persistence\FailureAwareInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\TaskContext;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Sources and targets are told when a task fails.
 */
class DataTransferProcessorFailureTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $log = [];

    private function createSource(?\Throwable $failError = null): DataSourceInterface&FailureAwareInterface&CompletableSourceInterface
    {
        return new class($this->log, $failError) implements DataSourceInterface, FailureAwareInterface, CompletableSourceInterface {
            /**
             * @param list<string> $log
             */
            public function __construct(private array &$log, private readonly ?\Throwable $failError)
            {
            }

            public function getRecords(array $configuration): iterable
            {
                yield ['id' => 1];
                yield ['id' => 2];
            }

            public function complete(ResultInterface $result): void
            {
                $this->log[] = 'source complete';
            }

            public function fail(\Throwable $error): void
            {
                $this->log[] = 'source fail: ' . $error->getMessage();
                if ($this->failError !== null) {
                    throw $this->failError;
                }
            }
        };
    }

    private function createTarget(?\Throwable $failError = null): DataTargetInterface&FailureAwareInterface
    {
        return new class($this->log, $failError) implements DataTargetInterface, FailureAwareInterface {
            /**
             * @param list<string> $log
             */
            public function __construct(private array &$log, private readonly ?\Throwable $failError)
            {
            }

            public function persist(array|object $object, ?array $configuration = null): bool
            {
                return true;
            }

            public function persistAll(mixed $result = null, ?array $configuration = null): void
            {
                $this->log[] = 'target persistAll';
            }

            public function fail(\Throwable $error): void
            {
                $this->log[] = 'target fail: ' . $error->getMessage();
                if ($this->failError !== null) {
                    throw $this->failError;
                }
            }
        };
    }

    private function createTask(DataSourceInterface $source, DataTargetInterface $target): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource($source);
        $task->setTarget($target);

        return $task;
    }

    private function failingPreProcessor(\Throwable $error): PreProcessorInterface
    {
        return new class($error) implements PreProcessorInterface {
            public function __construct(private readonly \Throwable $error)
            {
            }

            public function process(array $record): array
            {
                throw $this->error;
            }
        };
    }

    public function testNotifiesSourceAndTargetAndRethrowsOriginalException(): void
    {
        $error = new \RuntimeException('Record broken');
        $task = $this->createTask($this->createSource(), $this->createTarget());
        $task->setPreProcessors([$this->failingPreProcessor($error)]);

        try {
            (new DataTransferProcessor())->process($task);
            self::fail('The exception should be passed on.');
        } catch (\RuntimeException $exception) {
            self::assertSame($error, $exception);
        }

        self::assertSame(['source fail: Record broken', 'target fail: Record broken'], $this->log);
    }

    public function testNotifiesSourceAndTargetIfFinisherFails(): void
    {
        $error = new \RuntimeException('Finisher broken');
        $task = $this->createTask($this->createSource(), $this->createTarget());
        $task->setFinishers([new class($error) implements FinisherInterface {
            public function __construct(private readonly \Throwable $error)
            {
            }

            public function finish(TaskContext $context): void
            {
                throw $this->error;
            }
        }]);

        try {
            (new DataTransferProcessor())->process($task);
            self::fail('The exception should be passed on.');
        } catch (\RuntimeException $exception) {
            self::assertSame($error, $exception);
        }

        self::assertSame(
            ['target persistAll', 'source complete', 'source fail: Finisher broken', 'target fail: Finisher broken'],
            $this->log
        );
    }

    public function testExceptionOfFailDoesNotHideOriginalException(): void
    {
        $error = new \RuntimeException('Record broken');
        $task = $this->createTask(
            $this->createSource(new \LogicException('Source cleanup failed')),
            $this->createTarget(new \LogicException('Target cleanup failed'))
        );
        $task->setPreProcessors([$this->failingPreProcessor($error)]);
        $result = new TaskResult();

        try {
            (new DataTransferProcessor())->process($task, $result);
            self::fail('The exception should be passed on.');
        } catch (\RuntimeException $exception) {
            self::assertSame($error, $exception);
        }

        self::assertSame(['source fail: Record broken', 'target fail: Record broken'], $this->log);
        self::assertTrue($result->hasMessageWithId(DataTransferProcessor::ERROR_FAIL_THREW));
        $messages = array_map(static fn ($message): string => $message->getMessage(), $result->getMessages());
        self::assertCount(2, $messages);
        self::assertStringContainsString('LogicException: Source cleanup failed', $messages[0]);
        self::assertStringContainsString('LogicException: Target cleanup failed', $messages[1]);
    }

    public function testDoesNotNotifyOnSuccess(): void
    {
        (new DataTransferProcessor())->process($this->createTask($this->createSource(), $this->createTarget()));

        self::assertSame(['target persistAll', 'source complete'], $this->log);
    }
}
