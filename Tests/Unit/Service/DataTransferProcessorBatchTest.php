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

use CPSIT\ImportExportCore\Domain\Model\Dto\TaskDemand;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Event\BatchProcessed;
use CPSIT\ImportExportCore\Event\TaskFinished;
use CPSIT\ImportExportCore\Event\TaskStarted;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\BatchAwareTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RecordingEventDispatcher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';

class DataTransferProcessorBatchTest extends TestCase
{
    /**
     * @param iterable<mixed> $records
     */
    private function createTask(iterable $records, DataTargetInterface $target): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource(new ArraySource($records));
        $task->setTarget($target);

        return $task;
    }

    /**
     * @return list<array{id: int}>
     */
    private static function records(int $count): array
    {
        return array_map(static fn (int $id): array => ['id' => $id], range(1, $count));
    }

    /**
     * @param list<string> $log
     * @return \Generator<array{id: int}>
     */
    private static function generate(int $count, array &$log): \Generator
    {
        for ($id = 1; $id <= $count; ++$id) {
            $log[] = 'read:' . $id;
            yield ['id' => $id];
        }
    }

    public function testRejectsBatchSizeBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794400001);

        new DataTransferProcessor(0);
    }

    public function testFlushesBatchAwareTargetAfterEachBatch(): void
    {
        $log = [];
        $target = new BatchAwareTarget($log);

        $result = (new DataTransferProcessor(2))->process($this->createTask(self::records(5), $target));

        self::assertSame([2, 4, 5], $target->flushedAt);
        self::assertSame(['flush:2', 'flush:4', 'flush:5', 'persistAll'], $log);
        self::assertSame(3, $result->getStatistics()->getBatches());
    }

    public function testReadsRecordsLazily(): void
    {
        $log = [];
        $target = new BatchAwareTarget($log);
        $task = $this->createTask(self::generate(3, $log), $target);

        (new DataTransferProcessor(2))->process($task);

        self::assertSame(['read:1', 'read:2', 'flush:2', 'read:3', 'flush:3', 'persistAll'], $log);
    }

    public function testBuildQueueReadsGeneratorsInAdvance(): void
    {
        $log = [];
        $task = $this->createTask(self::generate(2, $log), new InMemoryTarget());
        $demand = new TaskDemand();
        $demand->setTasks([$task]);
        $subject = new DataTransferProcessor();

        $subject->buildQueue($demand);

        self::assertSame(['read:1', 'read:2'], $log);
        self::assertSame(['task' => [['id' => 1], ['id' => 2]]], $subject->getQueue());
    }

    public function testDispatchesEvents(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $task = $this->createTask(self::records(3), new InMemoryTarget());

        $result = (new DataTransferProcessor(2, true, $dispatcher))->process($task);

        self::assertCount(4, $dispatcher->events);
        [$started, $first, $second, $finished] = $dispatcher->events;
        self::assertInstanceOf(TaskStarted::class, $started);
        self::assertSame($task, $started->task);
        self::assertSame('task', $started->taskKey);
        self::assertInstanceOf(BatchProcessed::class, $first);
        self::assertSame([1, 2], [$first->batchNumber, $first->recordCount]);
        self::assertSame($result, $first->result);
        self::assertInstanceOf(BatchProcessed::class, $second);
        self::assertSame([2, 1], [$second->batchNumber, $second->recordCount]);
        self::assertInstanceOf(TaskFinished::class, $finished);
        self::assertSame(3, $finished->recordCount);
        self::assertSame($result, $finished->result);
    }

    public function testDispatchesStartAndFinishWithoutRecords(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        (new DataTransferProcessor(2, true, $dispatcher))->process($this->createTask([], new InMemoryTarget()));

        self::assertSame(['event:TaskStarted', 'event:TaskFinished'], $dispatcher->log);
    }

    public function testCountsRecords(): void
    {
        $target = new InMemoryTarget();
        $target->disabledForIds = [2];

        $statistics = (new DataTransferProcessor())->process($this->createTask(self::records(3), $target))->getStatistics();

        self::assertSame(3, $statistics->getRead());
        self::assertSame(2, $statistics->getPersisted());
        self::assertSame(1, $statistics->getSkipped());
        self::assertSame(1, $statistics->getBatches());
    }

    public function testDoesNotCollectRecordsIfDisabled(): void
    {
        $target = new InMemoryTarget();

        $result = (new DataTransferProcessor(2, false))->process($this->createTask(self::records(3), $target));

        self::assertSame([], $result->toArray());
        self::assertCount(3, $target->persisted);
        self::assertSame(3, $result->getStatistics()->getPersisted());
    }
}
