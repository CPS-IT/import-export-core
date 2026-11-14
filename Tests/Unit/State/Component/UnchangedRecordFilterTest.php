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

namespace CPSIT\ImportExportCore\Tests\Unit\State\Component;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\State\Component\UnchangedRecordFilter;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use PHPUnit\Framework\TestCase;

class UnchangedRecordFilterTest extends TestCase
{
    private InMemoryIdMap $idMap;

    private UnchangedRecordFilter $subject;

    protected function setUp(): void
    {
        $this->idMap = new InMemoryIdMap();
        $this->subject = new UnchangedRecordFilter($this->idMap);
        $this->subject->setConfiguration(['namespace' => 'pages', 'sourceIdField' => 'uid']);
    }

    public function testRequiresNamespaceAndSourceIdField(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new UnchangedRecordFilter($this->idMap))->setConfiguration(['namespace' => 'pages']);
    }

    public function testAcceptsRecordWithoutMapping(): void
    {
        self::assertTrue($this->subject->accepts(['uid' => 1, 'title' => 'Home']));
    }

    public function testRejectsRecordWithSameChecksum(): void
    {
        $record = ['uid' => 1, 'title' => 'Home'];
        $this->idMap->set(new IdMapping('pages', '1', '100', IdMapping::recordChecksum($record, 'uid')));

        self::assertFalse($this->subject->accepts($record));
    }

    public function testIdentifiesProcessedRecordBySourceIdAndIgnoresItsIdField(): void
    {
        // A pre-processor replaced the source ID with the target ID.
        $this->idMap->set(new IdMapping('pages', '1', '100', IdMapping::recordChecksum(['title' => 'Home'], 'uid')));

        self::assertFalse($this->subject->acceptsProcessed(['uid' => '100', 'title' => 'Home'], ['uid' => 1, 'title' => 'Home']));
        self::assertTrue($this->subject->acceptsProcessed(['uid' => '100', 'title' => 'Start'], ['uid' => 1, 'title' => 'Home']));
    }

    public function testAcceptsChangedRecord(): void
    {
        $this->idMap->set(new IdMapping('pages', '1', '100', IdMapping::recordChecksum(['uid' => 1, 'title' => 'Home'], 'uid')));

        self::assertTrue($this->subject->accepts(['uid' => 1, 'title' => 'Start']));
    }

    public function testAcceptsRecordWithoutSourceId(): void
    {
        self::assertTrue($this->subject->accepts(['title' => 'Home']));
    }

    public function testComparesWithinConfiguredNamespace(): void
    {
        $record = ['uid' => 1, 'title' => 'Home'];
        $this->idMap->set(new IdMapping('tt_content', '1', '100', IdMapping::recordChecksum($record, 'uid')));

        self::assertTrue($this->subject->accepts($record));
    }
}
