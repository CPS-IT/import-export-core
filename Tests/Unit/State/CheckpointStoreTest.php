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
use CPSIT\ImportExportCore\State\InMemoryCheckpointStore;
use CPSIT\ImportExportCore\State\NullCheckpointStore;
use PHPUnit\Framework\TestCase;

class CheckpointStoreTest extends TestCase
{
    public function testInMemoryStoreReturnsInitialCheckpointForUnknownTask(): void
    {
        $checkpoint = (new InMemoryCheckpointStore())->get('task');

        self::assertSame('task', $checkpoint->taskKey);
        self::assertTrue($checkpoint->isInitial());
    }

    public function testInMemoryStoreSavesReplacesAndClearsPerTask(): void
    {
        $subject = new InMemoryCheckpointStore();
        $other = new Checkpoint('other', 1, 10);
        $subject->save(new Checkpoint('task', 1, 10));
        $subject->save($other);

        $latest = new Checkpoint('task', 2, 20);
        $subject->save($latest);
        self::assertSame($latest, $subject->get('task'));

        $subject->clear('task');
        self::assertTrue($subject->get('task')->isInitial());
        self::assertSame($other, $subject->get('other'));
    }

    public function testNullStoreStoresNothing(): void
    {
        $subject = new NullCheckpointStore();

        $subject->save(new Checkpoint('task', 1, 10));
        $subject->clear('task');

        self::assertTrue($subject->get('task')->isInitial());
    }
}
