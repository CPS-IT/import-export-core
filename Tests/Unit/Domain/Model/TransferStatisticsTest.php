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

namespace CPSIT\ImportExportCore\Tests\Unit\Domain\Model;

use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferStatistics;
use CPSIT\ImportExportCore\Event\NullEventDispatcher;
use PHPUnit\Framework\TestCase;

class TransferStatisticsTest extends TestCase
{
    public function testCounts(): void
    {
        $subject = new TransferStatistics();

        $subject->countRead();
        $subject->countRead(2);
        $subject->countPersisted(2);
        $subject->countSkipped();
        $subject->countBatch();

        self::assertSame([3, 2, 1, 1], [$subject->getRead(), $subject->getPersisted(), $subject->getSkipped(), $subject->getBatches()]);
    }

    public function testTaskResultHasStatistics(): void
    {
        $result = new TaskResult();

        self::assertSame(0, $result->getStatistics()->getRead());
        self::assertSame($result->getStatistics(), $result->getStatistics());
    }

    public function testNullEventDispatcherReturnsEvent(): void
    {
        $event = new \stdClass();

        self::assertSame($event, (new NullEventDispatcher())->dispatch($event));
    }
}
