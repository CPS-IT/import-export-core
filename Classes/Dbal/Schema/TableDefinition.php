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

namespace CPSIT\ImportExportCore\Dbal\Schema;

use CPSIT\ImportExportCore\Dbal\Identifier;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;

/**
 * Helpers for the table definitions of the DBAL stores. All columns have a
 * default or allow NULL, so they can be added to existing tables.
 *
 * @internal
 */
final class TableDefinition
{
    /**
     * Checks a table name given to a store, e.g. `import_export_id_map` or
     * `schema.import_export_id_map`.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertValidName(string $table): void
    {
        if (!Identifier::isTable($table)) {
            throw new \InvalidArgumentException(\sprintf('Invalid table name "%s".', $table), 1794700101);
        }
    }

    public static function table(string $name): TableEditor
    {
        $parts = explode('.', $name, 2);

        return isset($parts[1])
            ? Table::editor()->setUnquotedName($parts[1], $parts[0])
            : Table::editor()->setUnquotedName($parts[0]);
    }

    /**
     * @param non-empty-string $name
     */
    public static function string(string $name, int $length = 255): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::STRING)
            ->setLength($length)
            ->setDefaultValue('')
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function text(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::TEXT)
            ->setNotNull(false)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function integer(string $name, string $type = Types::INTEGER): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName($type)
            ->setDefaultValue(0)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function dateTime(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::DATETIME_IMMUTABLE)
            ->setNotNull(false)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function autoIncrement(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::BIGINT)
            ->setAutoincrement(true)
            ->create();
    }

    /**
     * @param non-empty-string $first
     * @param non-empty-string ...$other
     */
    public static function primaryKey(string $first, string ...$other): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames($first, ...$other)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $first
     * @param non-empty-string ...$other
     */
    public static function index(string $name, string $first, string ...$other): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames($first, ...$other)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $first
     * @param non-empty-string ...$other
     */
    public static function unique(string $name, string $first, string ...$other): UniqueConstraint
    {
        return UniqueConstraint::editor()->setUnquotedName($name)->setUnquotedColumnNames($first, ...$other)->create();
    }

    /**
     * Index names must be unique per database on some platforms, so they
     * start with the table name.
     *
     * @return non-empty-string
     */
    public static function indexName(string $table, string $suffix): string
    {
        return str_replace('.', '_', $table) . '_' . $suffix;
    }
}
