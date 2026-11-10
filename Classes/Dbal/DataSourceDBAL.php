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
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\ResumableSourceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Reads records from a database table via Doctrine DBAL. Requires the package
 * doctrine/dbal.
 *
 * Configuration:
 *
 * * `table` (string, required): table name, optionally `schema.table`
 * * `connection` (string, default `default`): name of the connection in the
 *   connection provider
 * * `fields` (list of strings, default all fields)
 * * `where` (map of field => value): equality conditions combined with AND;
 *   null means IS NULL, a list of integers or strings means IN (...)
 * * `orderBy` (map of field => `ASC`|`DESC`); not together with `cursorField`
 * * `limit` (int): maximum number of records
 * * `cursorField` (string): a unique, sortable field, e.g. `uid`. Records are
 *   read in ascending order of this field, in pages of `pageSize` records,
 *   and an interrupted task continues after the last processed record. Must
 *   be one of `fields`, if fields are given.
 * * `pageSize` (int, default 1000): records per query with `cursorField`
 */
class DataSourceDBAL implements DataSourceInterface, ConfigurableInterface, ResumableSourceInterface
{
    use ConfigurableTrait;

    public const string KEY_CONNECTION = 'connection';
    public const string KEY_TABLE = 'table';
    public const string KEY_FIELDS = 'fields';
    public const string KEY_WHERE = 'where';
    public const string KEY_ORDER_BY = 'orderBy';
    public const string KEY_LIMIT = 'limit';
    public const string KEY_CURSOR_FIELD = 'cursorField';
    public const string KEY_PAGE_SIZE = 'pageSize';
    public const int DEFAULT_PAGE_SIZE = 1000;

    protected string $cursor = '';

    protected string $resumeCursor = '';

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
        $fields = $configuration[self::KEY_FIELDS] ?? [];
        if (!\is_array($fields) || !array_is_list($fields) || array_filter($fields, Identifier::isColumn(...)) !== $fields) {
            return false;
        }
        $where = $configuration[self::KEY_WHERE] ?? [];
        if (!\is_array($where) || !Conditions::isValid($where)) {
            return false;
        }
        if (!$this->isValidOrderBy($configuration[self::KEY_ORDER_BY] ?? [])) {
            return false;
        }
        foreach ([self::KEY_LIMIT, self::KEY_PAGE_SIZE] as $key) {
            if (isset($configuration[$key]) && (!\is_int($configuration[$key]) || $configuration[$key] < 1)) {
                return false;
            }
        }
        if (!isset($configuration[self::KEY_CURSOR_FIELD])) {
            return true;
        }
        $cursorField = $configuration[self::KEY_CURSOR_FIELD];

        return Identifier::isColumn($cursorField)
            && ($fields === [] || \in_array($cursorField, $fields, true))
            && empty($configuration[self::KEY_ORDER_BY]);
    }

    /**
     * @return \Generator<array<string, mixed>>
     */
    public function getRecords(array $configuration): iterable
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new \UnexpectedValueException('Invalid configuration for the DBAL data source.', 1794700011);
        }
        /** @var string $connectionName */
        $connectionName = $configuration[self::KEY_CONNECTION] ?? ConnectionProviderInterface::DEFAULT_CONNECTION;
        $connection = $this->connectionProvider->getConnection($connectionName);
        $this->cursor = $this->resumeCursor;

        $cursorField = $configuration[self::KEY_CURSOR_FIELD] ?? null;
        if (\is_string($cursorField)) {
            yield from $this->readPages($connection, $configuration, $cursorField);

            return;
        }

        $query = $this->createQuery($connection, $configuration);
        /** @var array<string, string> $orderBy */
        $orderBy = $configuration[self::KEY_ORDER_BY] ?? [];
        foreach ($orderBy as $field => $direction) {
            $query->addOrderBy(Identifier::quote($connection, $field), strtoupper($direction));
        }
        if (isset($configuration[self::KEY_LIMIT]) && \is_int($configuration[self::KEY_LIMIT])) {
            $query->setMaxResults($configuration[self::KEY_LIMIT]);
        }

        yield from $query->executeQuery()->iterateAssociative();
    }

    /**
     * Returns the value of the cursor field of the last record read, or the
     * cursor passed to `resumeFrom()` if no record was read since. '' without
     * cursor field.
     */
    public function getCursor(): string
    {
        return $this->cursor;
    }

    public function resumeFrom(string $cursor): void
    {
        $this->resumeCursor = $cursor;
    }

    /**
     * Keyset pagination: each page starts after the cursor of the previous
     * one, so the queries stay fast for large tables.
     *
     * @param array<string, mixed> $configuration
     * @return \Generator<array<string, mixed>>
     */
    protected function readPages(Connection $connection, array $configuration, string $cursorField): \Generator
    {
        $pageSize = $configuration[self::KEY_PAGE_SIZE] ?? self::DEFAULT_PAGE_SIZE;
        $limit = $configuration[self::KEY_LIMIT] ?? PHP_INT_MAX;
        if (!\is_int($pageSize) || !\is_int($limit)) {
            return;
        }
        $quotedCursorField = Identifier::quote($connection, $cursorField);
        $read = 0;

        while ($read < $limit) {
            $size = min($pageSize, $limit - $read);
            $query = $this->createQuery($connection, $configuration)
                ->orderBy($quotedCursorField, 'ASC')
                ->setMaxResults($size);
            if ($this->cursor !== '') {
                $query->andWhere(
                    $query->expr()->gt(
                        $quotedCursorField,
                        $query->createNamedParameter($this->cursor, ParameterType::STRING)
                    )
                );
            }

            $count = 0;
            foreach ($query->executeQuery()->iterateAssociative() as $record) {
                $value = $record[$cursorField] ?? null;
                if (!\is_scalar($value)) {
                    throw new \UnexpectedValueException(
                        \sprintf('Cursor field "%s" is empty or missing in a record.', $cursorField),
                        1794700012
                    );
                }
                $this->cursor = (string)$value;
                ++$count;
                yield $record;
            }
            $read += $count;
            if ($count < $size) {
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function createQuery(Connection $connection, array $configuration): QueryBuilder
    {
        /** @var string $table */
        $table = $configuration[self::KEY_TABLE];
        /** @var list<string> $fields */
        $fields = $configuration[self::KEY_FIELDS] ?? [];
        $query = $connection->createQueryBuilder()
            ->from(Identifier::quote($connection, $table));
        if ($fields === []) {
            $query->select('*');
        } else {
            $query->select(...array_map(static fn (string $field): string => Identifier::quote($connection, $field), $fields));
        }
        /** @var array<string, mixed> $where */
        $where = $configuration[self::KEY_WHERE] ?? [];
        Conditions::apply($query, $connection, $where);

        return $query;
    }

    private function isValidOrderBy(mixed $orderBy): bool
    {
        if (!\is_array($orderBy)) {
            return false;
        }
        foreach ($orderBy as $field => $direction) {
            if (!Identifier::isColumn($field)
                || !\is_string($direction)
                || !\in_array(strtoupper($direction), ['ASC', 'DESC'], true)
            ) {
                return false;
            }
        }

        return true;
    }
}
