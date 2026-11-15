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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Structured filter (option `filter` of {@see DataSourceDBAL}), translated
 * into parameterized SQL. No SQL from the configuration: field and table
 * names are checked identifiers, values are parameters. A condition is one of:
 *
 * - `{all: [...]}`, `{any: [...]}`, `{not: condition}`
 * - `{field, op, value}` with op `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`,
 *   `notIn`, or `{field, op: isNull|isNotNull}`
 * - a relative date as value: `{date: "-P2Y", from: "today"|"now",
 *   format: "timestamp"|"date"|"datetime"}`, relative to the `$now` passed
 *   to {@see self::apply()} and in its time zone (`today -2 years` of
 *   TYPO3/strtotime)
 * - `{exists: {table, match: {column of table: column of the outer table},
 *   filter?}}` and `notExists`: a correlated subquery on a related table;
 *   `filter` refers to the columns of that table
 *
 * Nesting is limited to {@see self::MAX_DEPTH} levels.
 */
final class Filter
{
    public const int MAX_DEPTH = 8;

    private const array OPERATORS = ['=', '<>', '<', '<=', '>', '>=', 'in', 'notIn', 'isNull', 'isNotNull'];

    private int $aliases = 0;

    private function __construct(
        private readonly QueryBuilder $query,
        private readonly Connection $connection,
    ) {
    }

    public static function isValid(mixed $condition, int $depth = 0): bool
    {
        if (!\is_array($condition) || $depth >= self::MAX_DEPTH) {
            return false;
        }
        $keys = array_keys($condition);
        sort($keys);

        return match ($keys) {
            ['all'], ['any'] => self::isNonEmptyList($condition[$keys[0]])
                && [] === array_filter($condition[$keys[0]], static fn (mixed $item): bool => !self::isValid($item, $depth + 1)),
            ['not'] => self::isValid($condition['not'], $depth + 1),
            ['exists'], ['notExists'] => self::isValidExists($condition[$keys[0]], $depth),
            ['field', 'op'] => Identifier::isColumn($condition['field']) && \in_array($condition['op'], ['isNull', 'isNotNull'], true),
            ['field', 'op', 'value'] => Identifier::isColumn($condition['field']) && self::isValidComparison($condition['op'], $condition['value']),
            default => false,
        };
    }

    /**
     * Adds the condition to the query (AND). Relative dates are computed from
     * `$now`, in its time zone.
     *
     * @param array<mixed> $condition as checked by isValid()
     */
    public static function apply(QueryBuilder $query, Connection $connection, string $table, array $condition, \DateTimeImmutable $now): void
    {
        $filter = new self($query, $connection);
        $query->andWhere($filter->build($condition, Identifier::quote($connection, $table), $now));
    }

    /**
     * @param array<mixed> $condition
     */
    private function build(array $condition, string $table, \DateTimeImmutable $now): string
    {
        if (\is_array($condition['all'] ?? null) || \is_array($condition['any'] ?? null)) {
            $all = \is_array($condition['all'] ?? null);
            /** @var list<array<mixed>> $items */
            $items = $condition[$all ? 'all' : 'any'];
            $parts = array_map(fn (array $item): string => '(' . $this->build($item, $table, $now) . ')', $items);

            return implode($all ? ' AND ' : ' OR ', $parts);
        }
        if (\is_array($condition['not'] ?? null)) {
            return 'NOT (' . $this->build($condition['not'], $table, $now) . ')';
        }
        foreach (['exists' => 'EXISTS', 'notExists' => 'NOT EXISTS'] as $key => $keyword) {
            if (\is_array($condition[$key] ?? null)) {
                return $keyword . ' (' . $this->subquery($condition[$key], $table, $now) . ')';
            }
        }
        $field = $table . '.' . $this->connection->quoteSingleIdentifier(\is_string($condition['field'] ?? null) ? $condition['field'] : '');
        $operator = $condition['op'] ?? null;
        $value = $condition['value'] ?? null;
        if (\is_array($value) && \array_key_exists('date', $value)) {
            $value = self::date($value, $now);
        }

        return match ($operator) {
            'isNull' => $field . ' IS NULL',
            'isNotNull' => $field . ' IS NOT NULL',
            'in', 'notIn' => $field . ('in' === $operator ? ' IN (' : ' NOT IN (') . $this->listParameter($value) . ')',
            default => $field . ' ' . (\is_string($operator) ? $operator : '=') . ' ' . $this->query->createNamedParameter($value, Conditions::type($value)),
        };
    }

    /**
     * @param array<mixed> $exists
     */
    private function subquery(array $exists, string $outer, \DateTimeImmutable $now): string
    {
        $alias = $this->connection->quoteSingleIdentifier('f' . ++$this->aliases);
        $table = \is_string($exists['table'] ?? null) ? $exists['table'] : '';
        $conditions = [];
        foreach (\is_array($exists['match'] ?? null) ? $exists['match'] : [] as $inner => $column) {
            $conditions[] = $alias . '.' . $this->connection->quoteSingleIdentifier((string)$inner) . ' = ' . $outer . '.' . $this->connection->quoteSingleIdentifier(\is_string($column) ? $column : '');
        }
        if (\is_array($exists['filter'] ?? null)) {
            $conditions[] = '(' . $this->build($exists['filter'], $alias, $now) . ')';
        }

        return 'SELECT 1 FROM ' . Identifier::quote($this->connection, $table) . ' ' . $alias . ' WHERE ' . implode(' AND ', $conditions);
    }

    private function listParameter(mixed $values): string
    {
        $values = \is_array($values) ? array_values($values) : [];
        $type = array_filter($values, \is_int(...)) === $values ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;

        return $this->query->createNamedParameter($values, $type);
    }

    /**
     * @param array<mixed> $value
     */
    private static function date(array $value, \DateTimeImmutable $now): int|string
    {
        $base = 'today' === ($value['from'] ?? 'now') ? $now->setTime(0, 0) : $now;
        $offset = \is_string($value['date']) ? $value['date'] : '';
        $interval = new \DateInterval(ltrim($offset, '+-'));
        $date = str_starts_with($offset, '-') ? $base->sub($interval) : $base->add($interval);

        return match ($value['format'] ?? 'timestamp') {
            'date' => $date->format('Y-m-d'),
            'datetime' => $date->format('Y-m-d H:i:s'),
            default => $date->getTimestamp(),
        };
    }

    private static function isValidComparison(mixed $operator, mixed $value): bool
    {
        if (!\is_string($operator) || !\in_array($operator, self::OPERATORS, true) || \in_array($operator, ['isNull', 'isNotNull'], true)) {
            return false;
        }
        if (\in_array($operator, ['in', 'notIn'], true)) {
            return self::isNonEmptyList($value)
                && [] === array_filter($value, static fn (mixed $item): bool => !\is_int($item) && !\is_string($item));
        }

        return \is_int($value) || \is_float($value) || \is_string($value) || \is_bool($value) || self::isValidDate($value);
    }

    private static function isValidDate(mixed $value): bool
    {
        if (!\is_array($value) || [] !== array_diff(array_keys($value), ['date', 'from', 'format'])) {
            return false;
        }
        if (!\is_string($value['date'] ?? null) || 1 !== preg_match('/^[+-]?P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/', $value['date'])) {
            return false;
        }

        return \in_array($value['from'] ?? 'now', ['now', 'today'], true)
            && \in_array($value['format'] ?? 'timestamp', ['timestamp', 'date', 'datetime'], true);
    }

    private static function isValidExists(mixed $exists, int $depth): bool
    {
        if (!\is_array($exists) || [] !== array_diff(array_keys($exists), ['table', 'match', 'filter'])) {
            return false;
        }
        $match = $exists['match'] ?? null;
        if (!Identifier::isTable($exists['table'] ?? null) || !\is_array($match) || [] === $match) {
            return false;
        }
        foreach ($match as $inner => $outer) {
            if (!Identifier::isColumn($inner) || !Identifier::isColumn($outer)) {
                return false;
            }
        }

        return !\array_key_exists('filter', $exists) || self::isValid($exists['filter'], $depth + 1);
    }

    /**
     * @phpstan-assert-if-true non-empty-list<mixed> $value
     */
    private static function isNonEmptyList(mixed $value): bool
    {
        return \is_array($value) && [] !== $value && array_is_list($value);
    }
}
