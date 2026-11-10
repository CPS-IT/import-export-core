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
use CPSIT\ImportExportCore\State\IdMapInterface;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\NullIdMapping;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Stores the ID map in a database table. Requires the package doctrine/dbal;
 * the table is created by the `SchemaInstaller`.
 */
final class DbalIdMap implements IdMapInterface, TableProviderInterface
{
    public const string DEFAULT_TABLE = 'import_export_id_map';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = self::DEFAULT_TABLE,
    ) {
        TableDefinition::assertValidName($table);
    }

    public function set(IdMapping $mapping): void
    {
        $values = [
            $this->quote('target_id') => $mapping->targetId,
            $this->quote('checksum') => $mapping->checksum,
        ];
        if ($this->has($mapping->namespace, $mapping->sourceId)) {
            $this->connection->update(
                $this->quote($this->table),
                $values,
                [$this->quote('namespace') => $mapping->namespace, $this->quote('source_id') => $mapping->sourceId]
            );

            return;
        }
        $this->connection->insert(
            $this->quote($this->table),
            [$this->quote('namespace') => $mapping->namespace, $this->quote('source_id') => $mapping->sourceId] + $values
        );
    }

    public function get(string $namespace, string $sourceId): IdMapping
    {
        $row = $this->select($namespace)
            ->andWhere($this->quote('source_id') . ' = :sourceId')
            ->setParameter('sourceId', $sourceId)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? new NullIdMapping($namespace, $sourceId) : $this->createMapping($row);
    }

    public function has(string $namespace, string $sourceId): bool
    {
        return $this->get($namespace, $sourceId)->exists();
    }

    /**
     * @return \Generator<IdMapping> ordered by source ID
     */
    public function all(string $namespace): iterable
    {
        $rows = $this->select($namespace)
            ->orderBy($this->quote('source_id'))
            ->executeQuery()
            ->iterateAssociative();
        foreach ($rows as $row) {
            yield $this->createMapping($row);
        }
    }

    public function getTables(): array
    {
        return [
            TableDefinition::table($this->table)
                ->setColumns(
                    TableDefinition::string('namespace'),
                    TableDefinition::string('source_id'),
                    TableDefinition::string('target_id'),
                    TableDefinition::string('checksum', 40),
                )
                ->addPrimaryKeyConstraint(TableDefinition::primaryKey('namespace', 'source_id'))
                ->create(),
        ];
    }

    private function select(string $namespace): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                $this->quote('namespace'),
                $this->quote('source_id'),
                $this->quote('target_id'),
                $this->quote('checksum')
            )
            ->from($this->quote($this->table))
            ->where($this->quote('namespace') . ' = :namespace')
            ->setParameter('namespace', $namespace);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createMapping(array $row): IdMapping
    {
        return new IdMapping(
            (string)$this->value($row, 'namespace'),
            (string)$this->value($row, 'source_id'),
            (string)$this->value($row, 'target_id'),
            (string)$this->value($row, 'checksum')
        );
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
