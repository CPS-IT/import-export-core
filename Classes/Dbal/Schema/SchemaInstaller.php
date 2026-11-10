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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;

/**
 * Creates the tables of table providers: missing tables are created, missing
 * columns are added to existing tables. Nothing is changed or dropped, so it
 * is safe to run repeatedly, e.g. in a setup command. Indexes are created with
 * their table only.
 */
final class SchemaInstaller
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Returns the statements `install()` would execute.
     *
     * @return list<string>
     */
    public function getStatements(TableProviderInterface ...$providers): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->createSchemaManager();
        $statements = [];
        foreach ($providers as $provider) {
            foreach ($provider->getTables() as $table) {
                $name = $table->getObjectName()->toString();
                if (!$schemaManager->tablesExist([$name])) {
                    array_push($statements, ...$platform->getCreateTableSQL($table));
                    continue;
                }
                $existing = $schemaManager->introspectTable($name);
                $extended = $this->addMissingColumns($existing, $table);
                if ($extended === $existing) {
                    continue;
                }
                $diff = $schemaManager->createComparator()->compareTables($existing, $extended);
                array_push($statements, ...$platform->getAlterTableSQL($diff));
            }
        }

        return $statements;
    }

    /**
     * Executes the statements and returns them.
     *
     * @return list<string>
     */
    public function install(TableProviderInterface ...$providers): array
    {
        $statements = $this->getStatements(...$providers);
        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }

        return $statements;
    }

    private function addMissingColumns(Table $existing, Table $table): Table
    {
        $missing = [];
        foreach ($table->getColumns() as $column) {
            if (!$existing->hasColumn($column->getObjectName()->getIdentifier()->getValue())) {
                $missing[] = $column;
            }
        }
        if ($missing === []) {
            return $existing;
        }
        $editor = $existing->edit();
        foreach ($missing as $column) {
            $editor->addColumn($column);
        }

        return $editor->create();
    }
}
