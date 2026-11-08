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
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Service\DemandProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';

class DemandProcessorTest extends TestCase
{
    /**
     * @param array<mixed> $records
     */
    private function createTask(string $identifier, array $records, InMemoryTarget $target): TransferTask
    {
        $task = new TransferTask();
        $task->setIdentifier($identifier);
        $task->setSource(new ArraySource($records));
        $task->setTarget($target);

        return $task;
    }

    public function testProcessesTasksInOrderWithSharedResult(): void
    {
        $target = new InMemoryTarget();
        $demand = new TaskDemand();
        $demand->setTasks([
            $this->createTask('first', [['id' => 1]], $target),
            $this->createTask('second', [['id' => 2], ['id' => 3]], $target),
        ]);
        $subject = new DemandProcessor(new DataTransferProcessor());

        $result = $subject->process($demand);

        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $target->persisted);
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $result->toArray());
        self::assertCount(2, $target->persistAllCalls);
        self::assertSame($result, $target->persistAllCalls[1]['result']);
    }

    public function testUsesGivenResult(): void
    {
        $result = new TaskResult();
        $demand = new TaskDemand();
        $demand->setTasks([$this->createTask('only', [['id' => 1]], new InMemoryTarget())]);
        $subject = new DemandProcessor(new DataTransferProcessor());

        self::assertSame($result, $subject->process($demand, $result));
    }

    public function testReturnsEmptyResultForEmptyDemand(): void
    {
        $subject = new DemandProcessor(new DataTransferProcessor());

        self::assertCount(0, $subject->process(new TaskDemand()));
    }

    public function testUsesRecordsQueuedInAdvance(): void
    {
        $target = new InMemoryTarget();
        $demand = new TaskDemand();
        $demand->setTasks([$this->createTask('queued', [['id' => 'source']], $target)]);
        $processor = new DataTransferProcessor();
        $processor->withQueue(['queued' => [['id' => 'queue']]]);
        $subject = new DemandProcessor($processor);

        $subject->process($demand);

        self::assertSame([['id' => 'queue']], $target->persisted);
    }
}
