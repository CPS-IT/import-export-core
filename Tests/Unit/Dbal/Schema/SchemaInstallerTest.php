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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\Schema;

use CPSIT\ImportExportCore\Dbal\Queue\DbalQueueRepository;
use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;
use CPSIT\ImportExportCore\Dbal\State\DbalCheckpointStore;
use CPSIT\ImportExportCore\Dbal\State\DbalIdMap;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class SchemaInstallerTest extends TestCase
{
    private Connection $connection;

    private SchemaInstaller $subject;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->subject = new SchemaInstaller($this->connection);
    }

    public function testCreatesMissingTables(): void
    {
        $statements = $this->subject->install(
            new DbalIdMap($this->connection),
            new DbalCheckpointStore($this->connection),
            new DbalQueueRepository($this->connection)
        );

        self::assertNotEmpty($statements);
        self::assertTrue($this->connection->createSchemaManager()->tablesExist([
            'import_export_id_map',
            'import_export_checkpoint',
            'import_export_queue_item',
        ]));
    }

    public function testDoesNothingIfTablesAreComplete(): void
    {
        $idMap = new DbalIdMap($this->connection);
        $this->subject->install($idMap);

        self::assertSame([], $this->subject->getStatements($idMap));
        self::assertSame([], $this->subject->install($idMap));
    }

    public function testAddsMissingColumnsAndKeepsData(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE import_export_id_map (namespace VARCHAR(255) NOT NULL, source_id VARCHAR(255) NOT NULL, target_id VARCHAR(255) NOT NULL, legacy INTEGER, PRIMARY KEY (namespace, source_id))'
        );
        $this->connection->insert('import_export_id_map', ['namespace' => 'pages', 'source_id' => '7', 'target_id' => '42', 'legacy' => 1]);
        $idMap = new DbalIdMap($this->connection);

        $this->subject->install($idMap);

        $columns = array_map('strtolower', array_keys($this->connection->fetchAssociative('SELECT * FROM import_export_id_map') ?: []));
        sort($columns);
        self::assertSame(['checksum', 'legacy', 'namespace', 'source_id', 'target_id'], $columns);
        self::assertSame('42', $idMap->get('pages', '7')->targetId);
        self::assertSame('', $idMap->get('pages', '7')->checksum);
    }

    public function testGetStatementsDoesNotExecute(): void
    {
        $statements = $this->subject->getStatements(new DbalCheckpointStore($this->connection, 'custom_checkpoint'));

        self::assertCount(1, $statements);
        self::assertStringContainsString('custom_checkpoint', $statements[0]);
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['custom_checkpoint']));
    }
}
