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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\Queue;

use CPSIT\ImportExportCore\Dbal\ConnectionRegistry;
use CPSIT\ImportExportCore\Dbal\DataTargetDBAL;
use CPSIT\ImportExportCore\Dbal\Queue\DbalQueueRepository;
use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Persistence\DataSourceQueue;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Dbal\ConnectionFactoryTrait;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A queue source and a DBAL target on the same connection: if the task fails,
 * the target rolls back its batch and the claimed items end up failed.
 */
class QueueToDbalFailureTest extends TestCase
{
    use ConnectionFactoryTrait;

    private Connection $connection;

    private DbalQueueRepository $repository;

    protected function setUp(): void
    {
        $this->connection = self::createConnection();
        $this->repository = new DbalQueueRepository($this->connection);
        (new SchemaInstaller($this->connection))->install($this->repository);
        $this->connection->executeStatement('CREATE TABLE news (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
        foreach ([1, 2, 3, 4] as $id) {
            $this->repository->enqueue('news', ['id' => $id, 'title' => 'News ' . $id]);
        }
    }

    private function createTask(int $failAtId): TransferTask
    {
        $connections = new ConnectionRegistry(['default' => $this->connection]);
        $source = new DataSourceQueue($this->repository);
        $source->setConfiguration(['identifier' => 'news', 'limit' => 4]);
        $target = new DataTargetDBAL($connections);
        $target->setConfiguration(['table' => 'news', 'key' => ['id']]);
        $task = new TransferTask();
        $task->setIdentifier('drain');
        $task->setSource($source);
        $task->setTarget($target);
        $task->setPreProcessors([new class($failAtId) implements PreProcessorInterface {
            public function __construct(private readonly int $failAtId)
            {
            }

            public function process(array $record): array
            {
                if ($record['id'] === $this->failAtId) {
                    throw new \RuntimeException('Record broken');
                }

                return $record;
            }
        }]);

        return $task;
    }

    private function countRows(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM news');

        return is_numeric($count) ? (int)$count : -1;
    }

    public function testClaimedItemsAreFailedAndOpenBatchIsRolledBack(): void
    {
        try {
            (new DataTransferProcessor(10))->process($this->createTask(3), new TaskResult());
            self::fail('The exception should be passed on.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Record broken', $exception->getMessage());
        }

        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->countRows());
        self::assertSame(4, $this->repository->count('news', QueueItemStatus::Failed));
        self::assertSame(0, $this->repository->count('news', QueueItemStatus::Processing));
    }

    public function testCommittedBatchesStayAndAllClaimedItemsAreFailed(): void
    {
        try {
            (new DataTransferProcessor(2))->process($this->createTask(4), new TaskResult());
            self::fail('The exception should be passed on.');
        } catch (\RuntimeException) {
        }

        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(2, $this->countRows());
        self::assertSame(4, $this->repository->count('news', QueueItemStatus::Failed));
        self::assertSame(0, $this->repository->count('news', QueueItemStatus::Processing));
    }
}
