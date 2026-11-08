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

namespace CPSIT\ImportExportCore\Tests\Unit\Domain\Model\Dto;

use CPSIT\ImportExportCore\Domain\Model\Dto\DemandInterface;
use CPSIT\ImportExportCore\Domain\Model\Dto\TaskDemand;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use PHPUnit\Framework\TestCase;

class TaskDemandTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(DemandInterface::class, new TaskDemand());
    }

    public function testTasksAreInitiallyEmpty(): void
    {
        self::assertSame([], (new TaskDemand())->getTasks());
    }

    public function testSetsTasks(): void
    {
        $subject = new TaskDemand();
        $tasks = [new TransferTask(), new TransferTask()];

        $subject->setTasks($tasks);

        self::assertSame($tasks, $subject->getTasks());
    }
}
