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

namespace CPSIT\ImportExportCore\Dbal\Queue;

use CPSIT\ImportExportCore\Dbal\Identifier;
use CPSIT\ImportExportCore\Dbal\Schema\TableDefinition;
use CPSIT\ImportExportCore\Dbal\Schema\TableProviderInterface;
use CPSIT\ImportExportCore\Queue\QueueItem;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use CPSIT\ImportExportCore\Queue\QueueRepositoryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * Stores queues in a database table. Requires the package doctrine/dbal; the
 * table is created by the `SchemaInstaller`.
 *
 * Records are stored as JSON. Several runners can claim items of the same
 * queue at the same time: each item is claimed by one of them only. A claim
 * may return fewer items than requested if another runner claimed some of
 * them in the meantime.
 */
final class DbalQueueRepository implements QueueRepositoryInterface, TableProviderInterface
{
    public const string DEFAULT_TABLE = 'import_export_queue_item';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = self::DEFAULT_TABLE,
    ) {
        TableDefinition::assertValidName($table);
    }

    public function enqueue(string $queue, array $data, bool $requeue = false): bool
    {
        $checksum = QueueItem::checksum($queue, $data);
        $id = $this->connection->createQueryBuilder()
            ->select($this->quote('id'))
            ->from($this->quote($this->table))
            ->where($this->quote('queue') . ' = :queue', $this->quote('checksum') . ' = :checksum')
            ->setParameter('queue', $queue)
            ->setParameter('checksum', $checksum)
            ->executeQuery()
            ->fetchOne();
        $now = new \DateTimeImmutable();

        if ($id !== false) {
            if (!$requeue) {
                return false;
            }
            $this->connection->createQueryBuilder()
                ->update($this->quote($this->table))
                ->set($this->quote('status'), ':status')
                ->set($this->quote('claim_token'), "''")
                ->set($this->quote('updated_at'), ':now')
                ->where($this->quote('id') . ' = :id')
                ->setParameter('status', QueueItemStatus::New->value, ParameterType::INTEGER)
                ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
                ->setParameter('id', $id)
                ->executeStatement();

            return true;
        }

        $this->connection->insert(
            $this->quote($this->table),
            [
                $this->quote('queue') => $queue,
                $this->quote('checksum') => $checksum,
                $this->quote('data') => QueueItem::encode($data),
                $this->quote('status') => QueueItemStatus::New->value,
                $this->quote('claim_token') => '',
                $this->quote('created_at') => $now,
                $this->quote('updated_at') => $now,
            ],
            [Types::STRING, Types::STRING, Types::TEXT, Types::SMALLINT, Types::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE]
        );

        return true;
    }

    /**
     * Selects new items, marks them with a random claim token, and returns the
     * items carrying that token. The status condition of the update ensures an
     * item is claimed once, also with concurrent runners.
     */
    public function claim(string $queue, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }
        $ids = $this->connection->createQueryBuilder()
            ->select($this->quote('id'))
            ->from($this->quote($this->table))
            ->where($this->quote('queue') . ' = :queue', $this->quote('status') . ' = :status')
            ->orderBy($this->quote('id'))
            ->setMaxResults($limit)
            ->setParameter('queue', $queue)
            ->setParameter('status', QueueItemStatus::New->value, ParameterType::INTEGER)
            ->executeQuery()
            ->fetchFirstColumn();
        if ($ids === []) {
            return [];
        }

        $token = bin2hex(random_bytes(16));
        $this->connection->createQueryBuilder()
            ->update($this->quote($this->table))
            ->set($this->quote('status'), ':processing')
            ->set($this->quote('claim_token'), ':token')
            ->set($this->quote('updated_at'), ':now')
            ->where($this->quote('id') . ' IN (:ids)', $this->quote('status') . ' = :new')
            ->setParameter('processing', QueueItemStatus::Processing->value, ParameterType::INTEGER)
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
            ->setParameter('new', QueueItemStatus::New->value, ParameterType::INTEGER)
            ->executeStatement();

        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->quote($this->table))
            ->where($this->quote('claim_token') . ' = :token', $this->quote('status') . ' = :processing')
            ->orderBy($this->quote('id'))
            ->setParameter('token', $token)
            ->setParameter('processing', QueueItemStatus::Processing->value, ParameterType::INTEGER)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->createItem(...), $rows);
    }

    public function complete(QueueItem $item): void
    {
        $this->setStatus($item, QueueItemStatus::Finished);
    }

    public function fail(QueueItem $item): void
    {
        $this->setStatus($item, QueueItemStatus::Failed);
    }

    public function release(string $queue): int
    {
        return (int)$this->connection->createQueryBuilder()
            ->update($this->quote($this->table))
            ->set($this->quote('status'), ':new')
            ->set($this->quote('claim_token'), "''")
            ->set($this->quote('updated_at'), ':now')
            ->where($this->quote('queue') . ' = :queue', $this->quote('status') . ' = :processing')
            ->setParameter('new', QueueItemStatus::New->value, ParameterType::INTEGER)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('queue', $queue)
            ->setParameter('processing', QueueItemStatus::Processing->value, ParameterType::INTEGER)
            ->executeStatement();
    }

    public function count(string $queue, QueueItemStatus $status): int
    {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->quote($this->table))
            ->where($this->quote('queue') . ' = :queue', $this->quote('status') . ' = :status')
            ->setParameter('queue', $queue)
            ->setParameter('status', $status->value, ParameterType::INTEGER)
            ->executeQuery()
            ->fetchOne();

        return is_numeric($count) ? (int)$count : 0;
    }

    public function getTables(): array
    {
        return [
            TableDefinition::table($this->table)
                ->setColumns(
                    TableDefinition::autoIncrement('id'),
                    TableDefinition::string('queue'),
                    TableDefinition::string('checksum', 40),
                    TableDefinition::text('data'),
                    TableDefinition::integer('status', Types::SMALLINT),
                    TableDefinition::string('claim_token', 32),
                    TableDefinition::dateTime('created_at'),
                    TableDefinition::dateTime('updated_at'),
                )
                ->addPrimaryKeyConstraint(TableDefinition::primaryKey('id'))
                ->addUniqueConstraint(
                    TableDefinition::unique(TableDefinition::indexName($this->table, 'checksum'), 'queue', 'checksum')
                )
                ->addIndex(TableDefinition::index(TableDefinition::indexName($this->table, 'status'), 'queue', 'status'))
                ->create(),
        ];
    }

    private function setStatus(QueueItem $item, QueueItemStatus $status): void
    {
        $exists = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($this->quote($this->table))
            ->where($this->quote('id') . ' = :id', $this->quote('queue') . ' = :queue')
            ->setParameter('id', $item->id)
            ->setParameter('queue', $item->queue)
            ->executeQuery()
            ->fetchOne() !== false;
        if (!$exists) {
            throw new \InvalidArgumentException(
                \sprintf('Queue item "%s" does not exist in queue "%s".', $item->id, $item->queue),
                1794400101
            );
        }
        $this->connection->createQueryBuilder()
            ->update($this->quote($this->table))
            ->set($this->quote('status'), ':status')
            ->set($this->quote('updated_at'), ':now')
            ->where($this->quote('id') . ' = :id')
            ->setParameter('status', $status->value, ParameterType::INTEGER)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $item->id)
            ->executeStatement();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createItem(array $row): QueueItem
    {
        $data = json_decode((string)$this->scalar($row, 'data'), true, 512, JSON_THROW_ON_ERROR);

        return new QueueItem(
            (string)$this->scalar($row, 'id'),
            (string)$this->scalar($row, 'queue'),
            (string)$this->scalar($row, 'checksum'),
            \is_array($data) ? $data : [],
            QueueItemStatus::from((int)$this->scalar($row, 'status')),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at')
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(array $row, string $column): \DateTimeImmutable
    {
        $value = Type::getType(Types::DATETIME_IMMUTABLE)
            ->convertToPHPValue($row[$column] ?? null, $this->connection->getDatabasePlatform());

        return $value instanceof \DateTimeImmutable ? $value : new \DateTimeImmutable('@0');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function scalar(array $row, string $column): string|int|float|bool|null
    {
        $value = $row[$column] ?? null;

        return \is_scalar($value) ? $value : null;
    }

    private function quote(string $name): string
    {
        return Identifier::quote($this->connection, $name);
    }
}
