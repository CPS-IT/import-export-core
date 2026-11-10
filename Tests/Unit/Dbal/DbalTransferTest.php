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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal;

use CPSIT\ImportExportCore\Dbal\ConnectionRegistry;
use CPSIT\ImportExportCore\Dbal\DataSourceDBAL;
use CPSIT\ImportExportCore\Dbal\DataTargetDBAL;
use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;
use CPSIT\ImportExportCore\Dbal\State\DbalCheckpointStore;
use CPSIT\ImportExportCore\Dbal\State\DbalIdMap;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Event\NullEventDispatcher;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\State\IdMapSettings;
use CPSIT\ImportExportCore\State\InMemoryCheckpointStore;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/**
 * Transfers between two databases with the DBAL source and target.
 */
class DbalTransferTest extends TestCase
{
    private Connection $legacy;

    private Connection $target;

    private ConnectionRegistry $connections;

    private InMemoryIdMap $idMap;

    private InMemoryCheckpointStore $checkpoints;

    protected function setUp(): void
    {
        $this->legacy = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->legacy->executeStatement('CREATE TABLE news (id INTEGER PRIMARY KEY, headline TEXT, deleted INTEGER)');
        foreach ([[11, 'One', 0], [12, 'Two', 1], [13, 'Three', 0], [14, 'Four', 0], [15, 'Five', 0]] as [$id, $headline, $deleted]) {
            $this->legacy->insert('news', ['id' => $id, 'headline' => $headline, 'deleted' => $deleted]);
        }
        $this->target = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->target->executeStatement(
            'CREATE TABLE tx_news (uid INTEGER PRIMARY KEY AUTOINCREMENT, import_id TEXT UNIQUE, title TEXT NOT NULL)'
        );
        $this->connections = new ConnectionRegistry(['legacy' => $this->legacy, 'default' => $this->target]);
        $this->idMap = new InMemoryIdMap();
        $this->checkpoints = new InMemoryCheckpointStore();
    }

    private function createTask(): TransferTask
    {
        $source = new DataSourceDBAL($this->connections);
        $source->setConfiguration([
            'connection' => 'legacy',
            'table' => 'news',
            'fields' => ['id', 'headline'],
            'where' => ['deleted' => 0],
            'cursorField' => 'id',
            'pageSize' => 2,
        ]);
        $target = new DataTargetDBAL($this->connections);
        $target->setConfiguration(['table' => 'tx_news', 'key' => ['import_id'], 'idField' => 'uid']);
        $task = new TransferTask();
        $task->setIdentifier('news');
        $task->setSource($source);
        $task->setTarget($target);
        $task->setPreProcessors([new class implements PreProcessorInterface {
            public function process(array $record): array
            {
                return ['import_id' => (string)$record['id'], 'title' => $record['headline']];
            }
        }]);
        $task->setIdMapSettings(new IdMapSettings('news', 'id'));

        return $task;
    }

    private function createProcessor(): DataTransferProcessor
    {
        return new DataTransferProcessor(
            batchSize: 2,
            eventDispatcher: new NullEventDispatcher(),
            idMap: $this->idMap,
            checkpointStore: $this->checkpoints
        );
    }

    public function testTransfersRecordsAndRecordsIds(): void
    {
        $this->createProcessor()->process($this->createTask(), new TaskResult());

        self::assertSame(
            [['uid' => 1, 'import_id' => '11', 'title' => 'One'], ['uid' => 2, 'import_id' => '13', 'title' => 'Three'], ['uid' => 3, 'import_id' => '14', 'title' => 'Four'], ['uid' => 4, 'import_id' => '15', 'title' => 'Five']],
            $this->target->fetchAllAssociative('SELECT * FROM tx_news ORDER BY uid')
        );
        self::assertSame('1', $this->idMap->get('news', '11')->targetId);
        self::assertSame('4', $this->idMap->get('news', '15')->targetId);
        self::assertFalse($this->target->isTransactionActive());
    }

    public function testSecondRunUpdatesRows(): void
    {
        $this->createProcessor()->process($this->createTask(), new TaskResult());
        $this->legacy->update('news', ['headline' => 'One, changed'], ['id' => 11]);

        $this->createProcessor()->process($this->createTask(), new TaskResult());

        self::assertSame(
            ['One, changed', 'Three', 'Four', 'Five'],
            $this->target->fetchFirstColumn('SELECT title FROM tx_news ORDER BY uid')
        );
        self::assertSame('1', $this->idMap->get('news', '11')->targetId);
    }

    public function testResumesAfterLastCommittedBatch(): void
    {
        $this->legacy->update('news', ['headline' => null], ['id' => 14]);
        try {
            $this->createProcessor()->process($this->createTask(), new TaskResult());
            self::fail('The NOT NULL constraint should have stopped the task.');
        } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException) {
            // The process would end here; its open transaction is rolled back.
            $this->target->rollBack();
        }

        self::assertSame(['11', '13'], $this->target->fetchFirstColumn('SELECT import_id FROM tx_news ORDER BY uid'));
        self::assertSame('13', $this->checkpoints->get('news')->cursor);

        $this->legacy->update('news', ['headline' => 'Four'], ['id' => 14]);
        $this->target->executeStatement('UPDATE tx_news SET title = \'not read again\'');
        $this->createProcessor()->process($this->createTask(), new TaskResult());

        self::assertSame(
            ['not read again', 'not read again', 'Four', 'Five'],
            $this->target->fetchFirstColumn('SELECT title FROM tx_news ORDER BY uid')
        );
        self::assertTrue($this->checkpoints->get('news')->isInitial());
    }

    public function testKeepsStateInDatabaseAcrossProcesses(): void
    {
        $state = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new SchemaInstaller($state))->install(new DbalIdMap($state), new DbalCheckpointStore($state));
        $createProcessor = static fn (): DataTransferProcessor => new DataTransferProcessor(
            batchSize: 2,
            eventDispatcher: new NullEventDispatcher(),
            idMap: new DbalIdMap($state),
            checkpointStore: new DbalCheckpointStore($state)
        );
        $this->legacy->update('news', ['headline' => null], ['id' => 14]);
        try {
            $createProcessor()->process($this->createTask(), new TaskResult());
            self::fail('The NOT NULL constraint should have stopped the task.');
        } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException) {
            $this->target->rollBack();
        }

        self::assertSame('13', (new DbalCheckpointStore($state))->get('news')->cursor);
        self::assertSame('2', (new DbalIdMap($state))->get('news', '13')->targetId);

        $this->legacy->update('news', ['headline' => 'Four'], ['id' => 14]);
        $createProcessor()->process($this->createTask(), new TaskResult());

        self::assertSame(['11', '13', '14', '15'], $this->target->fetchFirstColumn('SELECT import_id FROM tx_news ORDER BY uid'));
        self::assertSame('4', (new DbalIdMap($state))->get('news', '15')->targetId);
        self::assertTrue((new DbalCheckpointStore($state))->get('news')->isInitial());
    }
}
