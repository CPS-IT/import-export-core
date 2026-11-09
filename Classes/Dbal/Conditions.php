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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Builds equality conditions from a map of column names to values:
 *
 * * scalar: `column = value`
 * * null: `column IS NULL`
 * * non-empty list of integers or strings: `column IN (...)`
 *
 * All values are passed as parameters.
 *
 * @internal
 */
final class Conditions
{
    /**
     * @param array<mixed> $conditions
     */
    public static function isValid(array $conditions): bool
    {
        foreach ($conditions as $column => $value) {
            if (!Identifier::isColumn($column) || !self::isValidValue($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds the conditions to the query, combined with AND.
     *
     * @param array<string, mixed> $conditions as validated by `isValid()`
     */
    public static function apply(QueryBuilder $query, Connection $connection, array $conditions): void
    {
        foreach ($conditions as $column => $value) {
            $quoted = Identifier::quote($connection, $column);
            if ($value === null) {
                $query->andWhere($query->expr()->isNull($quoted));
                continue;
            }
            if (\is_array($value)) {
                $type = array_filter($value, \is_int(...)) === $value
                    ? ArrayParameterType::INTEGER
                    : ArrayParameterType::STRING;
                $query->andWhere($query->expr()->in($quoted, $query->createNamedParameter($value, $type)));
                continue;
            }
            $query->andWhere($query->expr()->eq($quoted, $query->createNamedParameter($value, self::type($value))));
        }
    }

    public static function type(mixed $value): ParameterType
    {
        return match (true) {
            $value === null => ParameterType::NULL,
            \is_int($value) => ParameterType::INTEGER,
            \is_bool($value) => ParameterType::BOOLEAN,
            default => ParameterType::STRING,
        };
    }

    private static function isValidValue(mixed $value): bool
    {
        if ($value === null || \is_scalar($value)) {
            return true;
        }
        if (!\is_array($value) || $value === [] || !array_is_list($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!\is_int($item) && !\is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
