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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\State;

use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;
use CPSIT\ImportExportCore\Dbal\State\DbalCheckpointStore;
use CPSIT\ImportExportCore\Dbal\State\DbalIdMap;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\NullIdMapping;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class DbalStateStoresTest extends TestCase
{
    private Connection $connection;

    private DbalIdMap $idMap;

    private DbalCheckpointStore $checkpoints;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->idMap = new DbalIdMap($this->connection);
        $this->checkpoints = new DbalCheckpointStore($this->connection);
        (new SchemaInstaller($this->connection))->install($this->idMap, $this->checkpoints);
    }

    public function testIdMapReturnsNullMappingForUnknownSourceId(): void
    {
        $mapping = $this->idMap->get('pages', '7');

        self::assertInstanceOf(NullIdMapping::class, $mapping);
        self::assertSame('pages', $mapping->namespace);
        self::assertSame('7', $mapping->sourceId);
        self::assertFalse($this->idMap->has('pages', '7'));
    }

    public function testIdMapStoresAndReplacesMappings(): void
    {
        $this->idMap->set(new IdMapping('pages', '7', '42', 'abc'));
        self::assertEquals(new IdMapping('pages', '7', '42', 'abc'), $this->idMap->get('pages', '7'));
        self::assertTrue($this->idMap->has('pages', '7'));

        $this->idMap->set(new IdMapping('pages', '7', '43', 'def'));

        self::assertEquals(new IdMapping('pages', '7', '43', 'def'), $this->idMap->get('pages', '7'));
        self::assertCount(1, [...$this->idMap->all('pages')]);
    }

    public function testIdMapSeparatesNamespaces(): void
    {
        $this->idMap->set(new IdMapping('pages', '8', '43'));
        $this->idMap->set(new IdMapping('pages', '7', '42'));
        $this->idMap->set(new IdMapping('tt_content', '7', '99'));

        self::assertSame('99', $this->idMap->get('tt_content', '7')->targetId);
        self::assertEquals(
            [new IdMapping('pages', '7', '42'), new IdMapping('pages', '8', '43')],
            [...$this->idMap->all('pages')]
        );
        self::assertSame([], [...$this->idMap->all('sys_file')]);
    }

    public function testIdMapUsesGivenTable(): void
    {
        $idMap = new DbalIdMap($this->connection, 'custom_id_map');
        (new SchemaInstaller($this->connection))->install($idMap);

        $idMap->set(new IdMapping('pages', '7', '42'));

        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM custom_id_map'));
        self::assertFalse($this->idMap->has('pages', '7'));
    }

    public function testCheckpointStoreReturnsInitialCheckpointForUnknownTask(): void
    {
        $checkpoint = $this->checkpoints->get('news');

        self::assertSame('news', $checkpoint->taskKey);
        self::assertTrue($checkpoint->isInitial());
    }

    public function testCheckpointStoreSavesReplacesAndClearsPerTask(): void
    {
        $this->checkpoints->save(new Checkpoint('news', 1, 100, '100'));
        $this->checkpoints->save(new Checkpoint('pages', 3, 300));
        $this->checkpoints->save(new Checkpoint('news', 2, 200, '200'));

        self::assertEquals(new Checkpoint('news', 2, 200, '200'), $this->checkpoints->get('news'));

        $this->checkpoints->clear('news');

        self::assertTrue($this->checkpoints->get('news')->isInitial());
        self::assertEquals(new Checkpoint('pages', 3, 300), $this->checkpoints->get('pages'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTableProvider(): array
    {
        return [
            'empty' => [''],
            'with space' => ['id map'],
            'injection' => ['id_map; DROP TABLE pages'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTableProvider')]
    public function testThrowsForInvalidTableName(string $table): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794700101);

        new DbalCheckpointStore($this->connection, $table);
    }
}
