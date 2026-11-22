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
use CPSIT\ImportExportCore\Dbal\Queries;
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
        $row = Queries::builder($this->connection)
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
            'batch_number' => $checkpoint->batchNumber,
            'records_processed' => $checkpoint->recordsProcessed,
            'source_cursor' => $checkpoint->cursor,
            'updated_at' => new \DateTimeImmutable(),
        ];
        $types = [
            'task_key' => Types::STRING,
            'batch_number' => Types::INTEGER,
            'records_processed' => Types::BIGINT,
            'source_cursor' => Types::TEXT,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ];
        $exists = Queries::builder($this->connection)
            ->select('1')
            ->from($this->quote($this->table))
            ->where($this->quote('task_key') . ' = :taskKey')
            ->setParameter('taskKey', $checkpoint->taskKey)
            ->executeQuery()
            ->fetchOne() !== false;
        if ($exists) {
            Queries::update($this->connection, $this->table, $values, ['task_key' => $checkpoint->taskKey], $types);

            return;
        }
        Queries::insert($this->connection, $this->table, ['task_key' => $checkpoint->taskKey] + $values, $types);
    }

    public function clear(string $taskKey): void
    {
        Queries::delete($this->connection, $this->table, ['task_key' => $taskKey]);
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
