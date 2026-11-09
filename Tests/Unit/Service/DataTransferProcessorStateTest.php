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
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\InMemoryCheckpointStore;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use CPSIT\ImportExportCore\State\NullCheckpointStore;
use CPSIT\ImportExportCore\State\NullIdMap;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\CursorSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FailingBatchTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\LogInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ResultFinisher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';

/**
 * Task context, checkpoints and resuming interrupted runs.
 */
class DataTransferProcessorStateTest extends TestCase
{
    private InMemoryCheckpointStore $checkpoints;

    protected function setUp(): void
    {
        $this->checkpoints = new InMemoryCheckpointStore();
    }

    private function createProcessor(int $batchSize = 2): DataTransferProcessor
    {
        return new DataTransferProcessor($batchSize, false, new NullEventDispatcher(), new NullIdMap(), $this->checkpoints);
    }

    private function createTask(DataSourceInterface $source, DataTargetInterface $target): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier('task');
        $task->setSource($source);
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

    public function testInitializersAndFinishersReceiveContext(): void
    {
        $idMap = new InMemoryIdMap();
        $initializer = new LogInitializer();
        $finisher = new ResultFinisher();
        $task = $this->createTask(new ArraySource(self::records(1)), new InMemoryTarget());
        $task->setInitializers([$initializer]);
        $task->setFinishers([$finisher]);
        $result = new TaskResult();

        (new DataTransferProcessor(idMap: $idMap, checkpointStore: $this->checkpoints))->process($task, $result);

        self::assertNotNull($initializer->context);
        self::assertSame($initializer->context, $finisher->context);
        self::assertSame('task', $initializer->context->taskKey);
        self::assertSame($task, $initializer->context->task);
        self::assertSame($result, $initializer->context->result);
        self::assertSame($idMap, $initializer->context->idMap);
        self::assertSame($this->checkpoints, $initializer->context->checkpointStore);
        self::assertFalse($initializer->context->isResumed());
    }

    public function testDefaultsToNullStores(): void
    {
        $initializer = new LogInitializer();
        $task = $this->createTask(new ArraySource(self::records(1)), new InMemoryTarget());
        $task->setInitializers([$initializer]);

        (new DataTransferProcessor())->process($task);

        self::assertInstanceOf(NullIdMap::class, $initializer->context?->idMap);
        self::assertInstanceOf(NullCheckpointStore::class, $initializer->context->checkpointStore);
    }

    public function testClearsCheckpointAfterCompletedTask(): void
    {
        $this->createProcessor()->process($this->createTask(new ArraySource(self::records(5)), new FailingBatchTarget()));

        self::assertTrue($this->checkpoints->get('task')->isInitial());
    }

    public function testSavesCheckpointAfterEachBatchOfBatchAwareTarget(): void
    {
        $target = new FailingBatchTarget(failAtId: 4);

        try {
            $this->createProcessor()->process($this->createTask(new ArraySource(self::records(5)), $target));
            self::fail('Expected the target to fail');
        } catch (\RuntimeException) {
        }

        self::assertSame([1, 2, 3], $target->ids);
        self::assertEquals(new Checkpoint('task', 1, 2), $this->checkpoints->get('task'));
    }

    public function testSavesNoCheckpointForTargetThatIsNotBatchAware(): void
    {
        $target = new InMemoryTarget();
        $source = new ArraySource((static function (): \Generator {
            yield ['id' => 1];
            yield ['id' => 2];
            yield ['id' => 3];
            throw new \RuntimeException('Reading failed', 1794500101);
        })());

        try {
            $this->createProcessor()->process($this->createTask($source, $target));
            self::fail('Expected the source to fail');
        } catch (\RuntimeException) {
        }

        self::assertTrue($this->checkpoints->get('task')->isInitial());
    }

    public function testResumeSkipsProcessedRecordsOfSourceThatIsNotResumable(): void
    {
        $this->checkpoints->save(new Checkpoint('task', 1, 2));
        $target = new FailingBatchTarget();
        $result = new TaskResult();

        $this->createProcessor()->process($this->createTask(new ArraySource(self::records(5)), $target), $result);

        self::assertSame([3, 4, 5], $target->ids);
        self::assertSame(3, $result->getStatistics()->getRead());
        self::assertTrue($this->checkpoints->get('task')->isInitial());
    }

    public function testResumableSourceContinuesFromCursor(): void
    {
        $source = new CursorSource(self::records(5));
        $target = new FailingBatchTarget(failAtId: 4);

        try {
            $this->createProcessor()->process($this->createTask($source, $target));
            self::fail('Expected the target to fail');
        } catch (\RuntimeException) {
        }
        self::assertEquals(new Checkpoint('task', 1, 2, '2'), $this->checkpoints->get('task'));

        $source = new CursorSource(self::records(5));
        $target = new FailingBatchTarget();
        $this->createProcessor()->process($this->createTask($source, $target));

        self::assertSame('2', $source->resumedFrom);
        self::assertSame([3, 4, 5], $target->ids);
    }

    public function testResumedRunContinuesBatchNumbersAndRecordCount(): void
    {
        $this->checkpoints->save(new Checkpoint('task', 1, 2));
        $target = new FailingBatchTarget(failAtId: 5);

        try {
            $this->createProcessor()->process($this->createTask(new ArraySource(self::records(6)), $target));
            self::fail('Expected the target to fail');
        } catch (\RuntimeException) {
        }

        self::assertEquals(new Checkpoint('task', 2, 4), $this->checkpoints->get('task'));
    }

    public function testContextOfResumedRunHoldsCheckpoint(): void
    {
        $checkpoint = new Checkpoint('task', 1, 2);
        $this->checkpoints->save($checkpoint);
        $initializer = new LogInitializer();
        $task = $this->createTask(new ArraySource(self::records(3)), new FailingBatchTarget());
        $task->setInitializers([$initializer]);

        $this->createProcessor()->process($task);

        self::assertTrue($initializer->context?->isResumed());
        self::assertSame($checkpoint, $initializer->context->resumedFrom);
    }

    public function testQueuedRecordsAreSkippedOnResume(): void
    {
        $this->checkpoints->save(new Checkpoint('task', 1, 2, '2'));
        $source = new CursorSource(self::records(5));
        $target = new FailingBatchTarget();
        $processor = $this->createProcessor()->withQueue(['task' => self::records(5)]);

        $processor->process($this->createTask($source, $target));

        self::assertSame('', $source->resumedFrom);
        self::assertSame([3, 4, 5], $target->ids);
    }
}
