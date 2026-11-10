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

namespace CPSIT\ImportExportCore\Dbal\State;

use CPSIT\ImportExportCore\Dbal\Identifier;
use CPSIT\ImportExportCore\Dbal\Schema\TableDefinition;
use CPSIT\ImportExportCore\Dbal\Schema\TableProviderInterface;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\CheckpointStoreInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Stores checkpoints in a database table. Requires the package doctrine/dbal;
 * the table is created by the `SchemaInstaller`.
 */
final class DbalCheckpointStore implements CheckpointStoreInterface, TableProviderInterface
{
    public const string DEFAULT_TABLE = 'import_export_checkpoint';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = self::DEFAULT_TABLE,
    ) {
        TableDefinition::assertValidName($table);
    }

    public function get(string $taskKey): Checkpoint
    {
        $row = $this->connection->createQueryBuilder()
            ->select($this->quote('batch_number'), $this->quote('records_processed'), $this->quote('source_cursor'))
            ->from($this->quote($this->table))
            ->where($this->quote('task_key') . ' = :taskKey')
            ->setParameter('taskKey', $taskKey)
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return new Checkpoint($taskKey);
        }

        return new Checkpoint(
            $taskKey,
            (int)$this->value($row, 'batch_number'),
            (int)$this->value($row, 'records_processed'),
            (string)$this->value($row, 'source_cursor')
        );
    }

    public function save(Checkpoint $checkpoint): void
    {
        $values = [
            $this->quote('batch_number') => $checkpoint->batchNumber,
            $this->quote('records_processed') => $checkpoint->recordsProcessed,
            $this->quote('source_cursor') => $checkpoint->cursor,
            $this->quote('updated_at') => new \DateTimeImmutable(),
        ];
        $types = [Types::INTEGER, Types::BIGINT, Types::TEXT, Types::DATETIME_IMMUTABLE];
        $exists = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($this->quote($this->table))
            ->where($this->quote('task_key') . ' = :taskKey')
            ->setParameter('taskKey', $checkpoint->taskKey)
            ->executeQuery()
            ->fetchOne() !== false;
        if ($exists) {
            $this->connection->update(
                $this->quote($this->table),
                $values,
                [$this->quote('task_key') => $checkpoint->taskKey],
                [...$types, Types::STRING]
            );

            return;
        }
        $this->connection->insert(
            $this->quote($this->table),
            [$this->quote('task_key') => $checkpoint->taskKey] + $values,
            [Types::STRING, ...$types]
        );
    }

    public function clear(string $taskKey): void
    {
        $this->connection->delete($this->quote($this->table), [$this->quote('task_key') => $taskKey]);
    }

    public function getTables(): array
    {
        return [
            TableDefinition::table($this->table)
                ->setColumns(
                    TableDefinition::string('task_key'),
                    TableDefinition::integer('batch_number'),
                    TableDefinition::integer('records_processed', Types::BIGINT),
                    TableDefinition::text('source_cursor'),
                    TableDefinition::dateTime('updated_at'),
                )
                ->addPrimaryKeyConstraint(TableDefinition::primaryKey('task_key'))
                ->create(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function value(array $row, string $column): string|int|float|bool|null
    {
        $value = $row[$column] ?? null;

        return \is_scalar($value) ? $value : null;
    }

    private function quote(string $name): string
    {
        return Identifier::quote($this->connection, $name);
    }
}
