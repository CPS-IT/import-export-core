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
use Doctrine\DBAL\Types\Type;

/**
 * Builds queries with the query builder of doctrine/dbal. Connection
 * subclasses may return another query builder from createQueryBuilder(), or
 * quote names in insert(), update() and delete() (TYPO3 does both), so these
 * methods of the connection are not used.
 *
 * Table and column names are passed unquoted and quoted once; values are
 * passed as parameters.
 *
 * @internal
 */
final class Queries
{
    public static function builder(Connection $connection): QueryBuilder
    {
        return new QueryBuilder($connection);
    }

    /**
     * @param array<string, mixed> $values by column
     * @param array<string, ParameterType|ArrayParameterType|Type|string> $types by column; defaults to the type of the value
     */
    public static function insert(Connection $connection, string $table, array $values, array $types = []): void
    {
        $query = self::builder($connection)->insert(Identifier::quote($connection, $table));
        foreach ($values as $column => $value) {
            $query->setValue(
                Identifier::quote($connection, $column),
                $query->createNamedParameter($value, $types[$column] ?? Conditions::type($value))
            );
        }
        $query->executeStatement();
    }

    /**
     * @param array<string, mixed> $values by column
     * @param array<string, mixed> $criteria as accepted by `Conditions::apply()`
     * @param array<string, ParameterType|ArrayParameterType|Type|string> $types of the values by column; defaults to the type of the value
     */
    public static function update(Connection $connection, string $table, array $values, array $criteria, array $types = []): void
    {
        $query = self::builder($connection)->update(Identifier::quote($connection, $table));
        foreach ($values as $column => $value) {
            $query->set(
                Identifier::quote($connection, $column),
                $query->createNamedParameter($value, $types[$column] ?? Conditions::type($value))
            );
        }
        Conditions::apply($query, $connection, $criteria);
        $query->executeStatement();
    }

    /**
     * @param array<string, mixed> $criteria as accepted by `Conditions::apply()`
     */
    public static function delete(Connection $connection, string $table, array $criteria): void
    {
        $query = self::builder($connection)->delete(Identifier::quote($connection, $table));
        Conditions::apply($query, $connection, $criteria);
        $query->executeStatement();
    }
}
