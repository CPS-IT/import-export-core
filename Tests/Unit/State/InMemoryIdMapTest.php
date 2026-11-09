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

use CPSIT\ImportExportCore\State\IdMapInterface;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use CPSIT\ImportExportCore\State\NullIdMapping;
use PHPUnit\Framework\TestCase;

class InMemoryIdMapTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(IdMapInterface::class, new InMemoryIdMap());
    }

    public function testReturnsNullMappingForUnknownSourceId(): void
    {
        $subject = new InMemoryIdMap();

        $mapping = $subject->get('pages', '7');

        self::assertInstanceOf(NullIdMapping::class, $mapping);
        self::assertFalse($mapping->exists());
        self::assertSame('pages', $mapping->namespace);
        self::assertSame('7', $mapping->sourceId);
        self::assertSame('', $mapping->targetId);
        self::assertFalse($subject->has('pages', '7'));
    }

    public function testStoresMapping(): void
    {
        $subject = new InMemoryIdMap();
        $mapping = new IdMapping('pages', '7', '42', 'abc');

        $subject->set($mapping);

        self::assertTrue($subject->has('pages', '7'));
        self::assertSame($mapping, $subject->get('pages', '7'));
        self::assertTrue($subject->get('pages', '7')->exists());
    }

    public function testReplacesMappingOfSameSourceId(): void
    {
        $subject = new InMemoryIdMap();
        $subject->set(new IdMapping('pages', '7', '42'));

        $subject->set(new IdMapping('pages', '7', '43'));

        self::assertSame('43', $subject->get('pages', '7')->targetId);
        self::assertCount(1, [...$subject->all('pages')]);
    }

    public function testSeparatesNamespaces(): void
    {
        $subject = new InMemoryIdMap();
        $pages = new IdMapping('pages', '7', '42');
        $content = new IdMapping('tt_content', '7', '99');

        $subject->set($pages);
        $subject->set($content);

        self::assertSame('42', $subject->get('pages', '7')->targetId);
        self::assertSame('99', $subject->get('tt_content', '7')->targetId);
        self::assertSame([$pages], [...$subject->all('pages')]);
        self::assertSame([], [...$subject->all('sys_file')]);
    }
}
