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

namespace CPSIT\ImportExportCore\Tests\Unit\Persistence;

use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Persistence\NullDataSource;
use CPSIT\ImportExportCore\Persistence\NullDataTarget;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use PHPUnit\Framework\TestCase;

class NullSourceAndTargetTest extends TestCase
{
    public function testSourceHasNoRecords(): void
    {
        self::assertSame([], (new NullDataSource())->getRecords(['any' => 'thing']));
    }

    public function testTargetAcceptsRecords(): void
    {
        $subject = new NullDataTarget();

        self::assertTrue($subject->persist(['id' => 1]));
        self::assertTrue($subject->persist(new \stdClass()));
        $subject->persistAll();
    }

    public function testTaskWithNullSourceAndTargetProcessesNothing(): void
    {
        $task = new TransferTask();
        $task->setIdentifier('nothing');
        $task->setSource(new NullDataSource());
        $task->setTarget(new NullDataTarget());

        $result = (new DataTransferProcessor())->process($task);

        self::assertSame([], $result->toArray());
        self::assertSame(0, $result->getStatistics()->getRead());
    }
}
