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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\Schema;

use CPSIT\ImportExportCore\Dbal\Schema\TableDefinition;
use CPSIT\ImportExportCore\Dbal\State\DbalIdMap;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TableDefinitionTest extends TestCase
{
    /**
     * @return array<string, array{AbstractPlatform, ?string}>
     */
    public static function binaryCollationProvider(): array
    {
        return [
            'MySQL 5.7' => [new MySQLPlatform(), 'utf8mb4_bin'],
            'MySQL 8.0' => [new MySQL80Platform(), 'utf8mb4_0900_bin'],
            'MySQL 8.4' => [new MySQL84Platform(), 'utf8mb4_0900_bin'],
            'MariaDB 10.6' => [new MariaDB1060Platform(), 'utf8mb4_nopad_bin'],
            'MariaDB 11.8' => [new MariaDB110700Platform(), 'utf8mb4_nopad_bin'],
            'PostgreSQL' => [new PostgreSQLPlatform(), null],
            'SQLite' => [new SQLitePlatform(), null],
        ];
    }

    #[DataProvider('binaryCollationProvider')]
    public function testBinaryStringSetsBinaryCollationOnMySqlAndMariaDb(AbstractPlatform $platform, ?string $collation): void
    {
        $column = TableDefinition::binaryString('source_id', $platform);

        self::assertSame($collation, $column->getPlatformOptions()['collation'] ?? null);
        self::assertSame(255, $column->getLength());
        self::assertSame('', $column->getDefault());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function serverVersionProvider(): array
    {
        return [
            'MySQL 5.7' => ['5.7.44', 'utf8mb4_bin'],
            'MySQL 8.0' => ['8.0.40', 'utf8mb4_0900_bin'],
            'MySQL 8.4' => ['8.4.0', 'utf8mb4_0900_bin'],
            'MariaDB 10.6' => ['10.6.28-MariaDB', 'utf8mb4_nopad_bin'],
            'MariaDB 11.8' => ['11.8.0-MariaDB', 'utf8mb4_nopad_bin'],
        ];
    }

    #[DataProvider('serverVersionProvider')]
    public function testIdMapKeysUseBinaryCollationOnMySqlAndMariaDb(string $serverVersion, string $collation): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $serverVersion]);
        $table = (new DbalIdMap($connection))->getTables()[0];

        self::assertSame($collation, $table->getColumn('namespace')->getPlatformOptions()['collation'] ?? null);
        self::assertSame($collation, $table->getColumn('source_id')->getPlatformOptions()['collation'] ?? null);
        self::assertArrayNotHasKey('collation', $table->getColumn('target_id')->getPlatformOptions());

        $sql = implode("\n", $connection->getDatabasePlatform()->getCreateTableSQL($table));
        self::assertStringContainsString('namespace VARCHAR(255) DEFAULT \'\' NOT NULL COLLATE `' . $collation . '`', $sql);
        self::assertStringContainsString('source_id VARCHAR(255) DEFAULT \'\' NOT NULL COLLATE `' . $collation . '`', $sql);
    }
}
