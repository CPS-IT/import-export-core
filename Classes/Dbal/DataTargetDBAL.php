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

namespace CPSIT\ImportExportCore\Dbal;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Persistence\BatchAwareInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Persistence\IdentifyingTargetInterface;
use Doctrine\DBAL\Connection;

/**
 * Writes records into a database table via Doctrine DBAL. Requires the package
 * doctrine/dbal.
 *
 * Records must be arrays of column => scalar or null. With `key`, a record
 * updates the existing row with the same values in the key fields, if there
 * is one; otherwise it is inserted. A record without any of the key fields
 * is inserted, e.g. a new record after `TargetIdPreProcessor`.
 *
 * Each batch is written in one transaction, committed in `flushBatch()`. If a
 * task fails, the rows of the current batch are not committed, and a resumed
 * run continues after the last committed batch.
 *
 * Configuration:
 *
 * * `table` (string, required): table name, optionally `schema.table`
 * * `connection` (string, default `default`): name of the connection in the
 *   connection provider
 * * `key` (list of strings): fields identifying an existing row, e.g.
 *   `['import_source', 'import_id']`. Without key, all records are inserted.
 * * `idField` (string, default `id`): primary key column. Its value is
 *   reported as target ID for the ID map: taken from the record, from the
 *   existing row on update, or from the generated ID after an insert. Set it
 *   to '' for tables without such a column; the target reports no IDs then.
 */
class DataTargetDBAL implements DataTargetInterface, ConfigurableInterface, BatchAwareInterface, IdentifyingTargetInterface
{
    use ConfigurableTrait;

    public const string KEY_CONNECTION = 'connection';
    public const string KEY_TABLE = 'table';
    public const string KEY_KEY = 'key';
    public const string KEY_ID_FIELD = 'idField';
    public const string DEFAULT_ID_FIELD = 'id';

    /**
     * Records of the current batch with their IDs.
     *
     * @var list<array{array<mixed>, string}>
     */
    protected array $written = [];

    /**
     * Whether the next `persist()` starts a new batch.
     */
    protected bool $batchFlushed = false;

    /**
     * Connections with an open transaction for the current batch.
     *
     * @var array<string, Connection>
     */
    protected array $transactions = [];

    public function __construct(protected readonly ConnectionProviderInterface $connectionProvider)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!Identifier::isTable($configuration[self::KEY_TABLE] ?? null)) {
            return false;
        }
        if (isset($configuration[self::KEY_CONNECTION])
            && (!\is_string($configuration[self::KEY_CONNECTION]) || $configuration[self::KEY_CONNECTION] === '')
        ) {
            return false;
        }
        if (isset($configuration[self::KEY_ID_FIELD])
            && $configuration[self::KEY_ID_FIELD] !== ''
            && !Identifier::isColumn($configuration[self::KEY_ID_FIELD])
        ) {
            return false;
        }
        $key = $configuration[self::KEY_KEY] ?? [];

        return \is_array($key) && array_is_list($key) && array_filter($key, Identifier::isColumn(...)) === $key;
    }

    /**
     * @return bool true if the record was inserted, false if it updated a row
     */
    public function persist(array|object $object, ?array $configuration = null): bool
    {
        if (!\is_array($object)) {
            throw new \UnexpectedValueException(
                \sprintf('Only arrays can be written into a database table, got %s.', get_debug_type($object)),
                1794700021
            );
        }
        $configuration ??= $this->configuration;
        if (!$this->isConfigurationValid($configuration)) {
            throw new \UnexpectedValueException('Invalid configuration for the DBAL data target.', 1794700022);
        }
        $this->assertValidRecord($object);

        /** @var string $connectionName */
        $connectionName = $configuration[self::KEY_CONNECTION] ?? ConnectionProviderInterface::DEFAULT_CONNECTION;
        $connection = $this->connectionProvider->getConnection($connectionName);
        /** @var string $table */
        $table = $configuration[self::KEY_TABLE];
        /** @var string $idField */
        $idField = $configuration[self::KEY_ID_FIELD] ?? self::DEFAULT_ID_FIELD;
        /** @var list<string> $key */
        $key = $configuration[self::KEY_KEY] ?? [];

        if ($this->batchFlushed) {
            $this->written = [];
            $this->batchFlushed = false;
        }
        if (!isset($this->transactions[$connectionName])) {
            $connection->beginTransaction();
            $this->transactions[$connectionName] = $connection;
        }

        $conditions = $this->keyConditions($key, $object);
        if ($conditions !== []) {
            $existingId = $this->findExisting($connection, $table, $idField, $conditions);
            if ($existingId !== null) {
                $update = $connection->createQueryBuilder()->update(Identifier::quote($connection, $table));
                foreach ($object as $column => $value) {
                    $update->set(
                        Identifier::quote($connection, (string)$column),
                        $update->createNamedParameter($value, Conditions::type($value))
                    );
                }
                Conditions::apply($update, $connection, $conditions);
                $update->executeStatement();
                $this->written[] = [$object, $existingId];

                return false;
            }
        }

        $connection->insert(
            Identifier::quote($connection, $table),
            $this->quoteColumns($connection, $object),
            $this->types($object)
        );
        $this->written[] = [$object, $this->insertedId($connection, $idField, $object)];

        return true;
    }

    /**
     * Commits the rows written since the last batch.
     */
    public function flushBatch(ResultInterface $result): void
    {
        $this->commit();
        $this->batchFlushed = true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): void
    {
        $this->commit();
    }

    /**
     * Returns the ID of a record of the current batch.
     */
    public function getTargetId(array|object $record): string
    {
        foreach ($this->written as [$written, $id]) {
            if ($written !== $record) {
                continue;
            }
            if ($id === '') {
                throw new \LogicException('The target did not get an ID for the record.', 1794700028);
            }

            return $id;
        }

        throw new \LogicException('The record was not written in the current batch.', 1794700024);
    }

    protected function commit(): void
    {
        foreach ($this->transactions as $connection) {
            $connection->commit();
        }
        $this->transactions = [];
    }

    /**
     * Requires a generated ID column. For tables without one, set `idField`
     * to '': depending on the platform, the result is '', the ID of an earlier
     * insert, or an error (PostgreSQL).
     */
    protected function lastInsertId(Connection $connection): string
    {
        try {
            return (string)$connection->lastInsertId();
        } catch (\Doctrine\DBAL\Exception) {
            return '';
        }
    }

    /**
     * Returns no conditions if the record has none of the key fields: it is
     * inserted then.
     *
     * @param list<string> $key
     * @param array<mixed> $record
     * @return array<string, mixed>
     */
    protected function keyConditions(array $key, array $record): array
    {
        $present = array_filter($key, static fn (string $field): bool => \array_key_exists($field, $record));
        if ($present === []) {
            return [];
        }
        $conditions = [];
        foreach ($key as $field) {
            if (!\array_key_exists($field, $record)) {
                throw new \UnexpectedValueException(
                    \sprintf('Key field "%s" is missing in the record.', $field),
                    1794700025
                );
            }
            $conditions[$field] = $record[$field];
        }

        return $conditions;
    }

    /**
     * Returns the ID of the row matching the conditions, '' if the target
     * reports no IDs, or null if there is no such row.
     *
     * @param array<string, mixed> $conditions
     */
    protected function findExisting(Connection $connection, string $table, string $idField, array $conditions): ?string
    {
        $query = $connection->createQueryBuilder()
            ->select($idField === '' ? '1' : Identifier::quote($connection, $idField))
            ->from(Identifier::quote($connection, $table))
            ->setMaxResults(1);
        Conditions::apply($query, $connection, $conditions);
        $id = $query->executeQuery()->fetchOne();
        if ($id === false) {
            return null;
        }

        return $idField === '' || !\is_scalar($id) ? '' : (string)$id;
    }

    /**
     * @param array<mixed> $record
     */
    protected function insertedId(Connection $connection, string $idField, array $record): string
    {
        if ($idField === '') {
            return '';
        }
        $id = $record[$idField] ?? null;
        if (\is_scalar($id) && $id !== '') {
            return (string)$id;
        }

        return $this->lastInsertId($connection);
    }

    /**
     * @param array<mixed> $record
     */
    protected function assertValidRecord(array $record): void
    {
        if ($record === []) {
            throw new \UnexpectedValueException('Cannot write an empty record.', 1794700026);
        }
        foreach ($record as $column => $value) {
            if (!Identifier::isColumn($column)) {
                throw new \UnexpectedValueException(
                    \sprintf('Invalid column name "%s".', $column),
                    1794700023
                );
            }
            if ($value !== null && !\is_scalar($value)) {
                throw new \UnexpectedValueException(
                    \sprintf('Value of column "%s" must be a scalar or null, got %s.', $column, get_debug_type($value)),
                    1794700027
                );
            }
        }
    }

    /**
     * @param array<mixed> $record
     * @return array<string, mixed>
     */
    protected function quoteColumns(Connection $connection, array $record): array
    {
        $quoted = [];
        foreach ($record as $column => $value) {
            $quoted[Identifier::quote($connection, (string)$column)] = $value;
        }

        return $quoted;
    }

    /**
     * @param array<mixed> $record
     * @return list<\Doctrine\DBAL\ParameterType>
     */
    protected function types(array $record): array
    {
        return array_values(array_map(Conditions::type(...), $record));
    }
}
