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

use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\NullIdMap;
use CPSIT\ImportExportCore\State\NullIdMapping;
use PHPUnit\Framework\TestCase;

class NullIdMapTest extends TestCase
{
    public function testStoresNothing(): void
    {
        $subject = new NullIdMap();

        $subject->set(new IdMapping('pages', '7', '42'));

        self::assertFalse($subject->has('pages', '7'));
        self::assertInstanceOf(NullIdMapping::class, $subject->get('pages', '7'));
        self::assertSame([], [...$subject->all('pages')]);
    }
}
