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

use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Event\NullEventDispatcher;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\State\Component\ResolveReferencePreProcessor;
use CPSIT\ImportExportCore\State\Component\TargetIdPreProcessor;
use CPSIT\ImportExportCore\State\Component\UnchangedRecordFilter;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\IdMapSettings;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FlushingIdentifyingTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\IdentifyingTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ProcessedRecordFilter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RejectingFilter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';

/**
 * Filters, recording of target IDs and incremental runs.
 */
class DataTransferProcessorIdMapTest extends TestCase
{
    private InMemoryIdMap $idMap;

    private DataTransferProcessor $subject;

    protected function setUp(): void
    {
        $this->idMap = new InMemoryIdMap();
        $this->subject = new DataTransferProcessor(eventDispatcher: new NullEventDispatcher(), idMap: $this->idMap);
    }

    /**
     * @param iterable<mixed> $records
     */
    private function createTask(iterable $records, DataTargetInterface $target, bool $withIdMap = true): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier('pages');
        $task->setSource(new ArraySource($records));
        $task->setTarget($target);
        if ($withIdMap) {
            $task->setIdMapSettings(new IdMapSettings('pages', 'uid'));
        }

        return $task;
    }

    public function testFiltersRunFirstAndSkipRejectedRecords(): void
    {
        $log = [];
        $filter = new RejectingFilter($log);
        $filter->rejectedIds = [2];
        $target = new InMemoryTarget();
        $task = $this->createTask([['id' => 1], ['id' => 2]], $target, false);
        $task->setFilters([$filter]);
        $task->setPreProcessors([new FieldPreProcessor($log)]);
        $task->getPreProcessors()[0]->setConfiguration(['field' => 'seen', 'value' => true]);
        $result = new TaskResult();

        $this->subject->process($task, $result);

        self::assertSame(['filter:1', 'preProcessor:1', 'filter:2'], $log);
        self::assertSame([['id' => 1, 'seen' => true]], $target->persisted);
        self::assertSame(2, $result->getStatistics()->getRead());
        self::assertSame(1, $result->getStatistics()->getSkipped());
        self::assertCount(2, $result->getMessages());
    }

    public function testDisabledFilterIsNotAsked(): void
    {
        $filter = new RejectingFilter();
        $filter->rejectedIds = [1];
        $filter->disabled = true;
        $target = new InMemoryTarget();
        $task = $this->createTask([['id' => 1]], $target, false);
        $task->setFilters([$filter]);

        $this->subject->process($task);

        self::assertSame(0, $filter->calls);
        self::assertCount(1, $target->persisted);
    }

    public function testThrowsForNonArrayRecordsWithFilters(): void
    {
        $task = $this->createTask(['not an array'], new InMemoryTarget(), false);
        $task->setFilters([new RejectingFilter()]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794260001);

        $this->subject->process($task);
    }

    public function testRecordsTargetIdAndChecksumOfPreProcessedRecord(): void
    {
        $log = [];
        $task = $this->createTask([['uid' => 7, 'title' => 'Home'], ['uid' => 8, 'title' => 'News']], new IdentifyingTarget());
        $preProcessor = new FieldPreProcessor($log);
        $preProcessor->setConfiguration(['field' => 'title', 'value' => 'changed']);
        $task->setPreProcessors([$preProcessor]);

        $this->subject->process($task);

        self::assertEquals(
            new IdMapping('pages', '7', '100', IdMapping::recordChecksum(['uid' => 7, 'title' => 'changed'], 'uid')),
            $this->idMap->get('pages', '7')
        );
        self::assertSame('101', $this->idMap->get('pages', '8')->targetId);
    }

    public function testRecordsNothingWithoutIdMapSettings(): void
    {
        $this->subject->process($this->createTask([['uid' => 7]], new IdentifyingTarget(), false));

        self::assertFalse($this->idMap->has('pages', '7'));
    }

    public function testRecordsNoMappingForSkippedRecords(): void
    {
        $target = new IdentifyingTarget();
        $filter = new RejectingFilter();
        $filter->rejectedIds = [7];
        $task = $this->createTask([['id' => 7, 'uid' => 7]], $target);
        $task->setFilters([$filter]);

        $this->subject->process($task);

        self::assertFalse($this->idMap->has('pages', '7'));
    }

    public function testThrowsIfTargetCannotReportIds(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794600031);

        $this->subject->process($this->createTask([['uid' => 7]], new InMemoryTarget()));
    }

    public function testThrowsIfSourceRecordHasNoId(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794600032);

        $this->subject->process($this->createTask([['title' => 'no uid']], new IdentifyingTarget()));
    }

    public function testResolvesReferencesToRecordsWrittenEarlierInTheSameRun(): void
    {
        $target = new IdentifyingTarget();
        $task = $this->createTask([['uid' => 1, 'pid' => 0], ['uid' => 2, 'pid' => 1]], $target);
        $resolver = new ResolveReferencePreProcessor($this->idMap);
        $resolver->setConfiguration(['field' => 'pid', 'namespace' => 'pages']);
        $task->setPreProcessors([$resolver]);

        $this->subject->process($task);

        self::assertSame(['uid' => 2, 'pid' => '100'], $target->records['101']);
    }

    public function testRecordsMappingsOfBatchAwareTargetAfterFlush(): void
    {
        $subject = new DataTransferProcessor(2, false, new NullEventDispatcher(), $this->idMap);
        $target = new FlushingIdentifyingTarget();

        $subject->process($this->createTask([['uid' => 7], ['uid' => 8], ['uid' => 9]], $target));

        self::assertSame('100', $this->idMap->get('pages', '7')->targetId);
        self::assertSame('101', $this->idMap->get('pages', '8')->targetId);
        self::assertSame('102', $this->idMap->get('pages', '9')->targetId);
    }

    public function testBatchAwareTargetResolvesReferencesToEarlierBatches(): void
    {
        $subject = new DataTransferProcessor(1, false, new NullEventDispatcher(), $this->idMap);
        $target = new FlushingIdentifyingTarget();
        $task = $this->createTask([['uid' => 1, 'pid' => 0], ['uid' => 2, 'pid' => 1]], $target);
        $resolver = new ResolveReferencePreProcessor($this->idMap);
        $resolver->setConfiguration(['field' => 'pid', 'namespace' => 'pages']);
        $task->setPreProcessors([$resolver]);

        $subject->process($task);

        self::assertSame(['uid' => 2, 'pid' => '100'], $target->records['101']);
    }

    public function testBatchAwareTargetCannotResolveReferencesWithinTheSameBatch(): void
    {
        $subject = new DataTransferProcessor(2, false, new NullEventDispatcher(), $this->idMap);
        $task = $this->createTask([['uid' => 1, 'pid' => 0], ['uid' => 2, 'pid' => 1]], new FlushingIdentifyingTarget());
        $resolver = new ResolveReferencePreProcessor($this->idMap);
        $resolver->setConfiguration(['field' => 'pid', 'namespace' => 'pages']);
        $task->setPreProcessors([$resolver]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794600012);

        $subject->process($task);
    }

    public function testIncrementalRunSkipsUnchangedRecords(): void
    {
        $filter = new UnchangedRecordFilter($this->idMap);
        $filter->setConfiguration(['namespace' => 'pages', 'sourceIdField' => 'uid']);

        $first = $this->createTask([['uid' => 1, 'title' => 'Home'], ['uid' => 2, 'title' => 'News']], new IdentifyingTarget());
        $first->setFilters([$filter]);
        $this->subject->process($first);

        $target = new IdentifyingTarget();
        $second = $this->createTask([['uid' => 1, 'title' => 'Home'], ['uid' => 2, 'title' => 'Latest news'], ['uid' => 3, 'title' => 'Contact']], $target);
        $second->setFilters([$filter]);
        $result = new TaskResult();
        $this->subject->process($second, $result);

        self::assertSame(
            [['uid' => 2, 'title' => 'Latest news'], ['uid' => 3, 'title' => 'Contact']],
            array_values($target->records)
        );
        self::assertSame(1, $result->getStatistics()->getSkipped());
        self::assertSame(IdMapping::recordChecksum(['uid' => 2, 'title' => 'Latest news'], 'uid'), $this->idMap->get('pages', '2')->checksum);
    }

    public function testIncrementalRunDetectsChangesMadeByPreProcessors(): void
    {
        // The pre-processor stands for values from other tables, e.g. a relation list.
        $run = function (string $categories) {
            $filter = new UnchangedRecordFilter($this->idMap);
            $filter->setConfiguration(['namespace' => 'pages', 'sourceIdField' => 'uid']);
            $log = [];
            $relations = new FieldPreProcessor($log);
            $relations->setConfiguration(['field' => 'categories', 'value' => $categories]);
            $target = new IdentifyingTarget();
            $task = $this->createTask([['uid' => 1, 'title' => 'Home']], $target);
            $task->setPreProcessors([$relations]);
            $task->setFilters([$filter]);
            $result = new TaskResult();
            $this->subject->process($task, $result);

            return [$result->getStatistics()->getPersisted(), $result->getStatistics()->getSkipped()];
        };

        self::assertSame([1, 0], $run('3,5'));
        self::assertSame([0, 1], $run('3,5'));
        self::assertSame([1, 0], $run('3,6'));
    }

    public function testIncrementalRunWithUpsertSkipsRecordsFromTheSecondRunOn(): void
    {
        // The target ID replaces the source ID in `uid` once a mapping exists.
        $run = function () {
            $filter = new UnchangedRecordFilter($this->idMap);
            $filter->setConfiguration(['namespace' => 'pages', 'sourceIdField' => 'uid']);
            $upsert = new TargetIdPreProcessor($this->idMap);
            $upsert->setConfiguration(['namespace' => 'pages', 'sourceIdField' => 'uid']);
            $task = $this->createTask([['uid' => 1, 'title' => 'Home']], new IdentifyingTarget());
            $task->setPreProcessors([$upsert]);
            $task->setFilters([$filter]);
            $result = new TaskResult();
            $this->subject->process($task, $result);

            return $result->getStatistics()->getSkipped();
        };

        self::assertSame(0, $run());
        self::assertSame(1, $run());
    }

    public function testIncrementalRunWithSeparateTargetIdFieldWritesOnlyChangedRecords(): void
    {
        // Source ID `email`, target ID `uid`: `uid` is missing in the first run
        // and set from the second run on, so it must not be part of the checksum.
        $run = function (array $records) {
            $filter = new UnchangedRecordFilter($this->idMap);
            $filter->setConfiguration(['namespace' => 'users', 'sourceIdField' => 'email', 'targetIdField' => 'uid']);
            $upsert = new TargetIdPreProcessor($this->idMap);
            $upsert->setConfiguration(['namespace' => 'users', 'sourceIdField' => 'email', 'targetIdField' => 'uid']);
            $task = $this->createTask($records, new IdentifyingTarget());
            $task->setIdMapSettings(new IdMapSettings('users', 'email', 'uid'));
            $task->setPreProcessors([$upsert]);
            $task->setFilters([$filter]);
            $result = new TaskResult();
            $this->subject->process($task, $result);

            return $result->getStatistics()->getSkipped();
        };
        $records = [
            ['email' => 'a@example.org', 'name' => 'A'],
            ['email' => 'b@example.org', 'name' => 'B'],
            ['email' => 'c@example.org', 'name' => 'C'],
        ];

        self::assertSame(0, $run($records));
        $records[1]['name'] = 'Bea';
        self::assertSame(2, $run($records));
        self::assertSame(3, $run($records));
    }

    public function testProcessedRecordFiltersRunAfterThePreProcessors(): void
    {
        $log = [];
        $filter = new ProcessedRecordFilter($log);
        $preProcessor = new FieldPreProcessor($log);
        $preProcessor->setConfiguration(['field' => 'title', 'value' => 'changed']);
        $task = $this->createTask([['id' => 1, 'uid' => 1, 'title' => 'Home']], new IdentifyingTarget());
        $task->setPreProcessors([$preProcessor]);
        $task->setFilters([$filter]);

        $this->subject->process($task);

        self::assertSame(['preProcessor:1', 'processedFilter:1'], $log);
        self::assertSame([[['id' => 1, 'uid' => 1, 'title' => 'changed'], ['id' => 1, 'uid' => 1, 'title' => 'Home']]], $filter->received);
    }
}
