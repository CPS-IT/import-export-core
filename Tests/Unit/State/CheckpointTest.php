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

namespace CPSIT\ImportExportCore\Tests\Unit\State;

use CPSIT\ImportExportCore\State\Checkpoint;
use PHPUnit\Framework\TestCase;

class CheckpointTest extends TestCase
{
    public function testIsInitialWithoutBatch(): void
    {
        $subject = new Checkpoint('task');

        self::assertTrue($subject->isInitial());
        self::assertSame(0, $subject->recordsProcessed);
        self::assertSame('', $subject->cursor);
    }

    public function testIsNotInitialAfterBatch(): void
    {
        self::assertFalse((new Checkpoint('task', 1, 100, '100'))->isInitial());
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794500001);

        new Checkpoint('task', -1);
    }
}
