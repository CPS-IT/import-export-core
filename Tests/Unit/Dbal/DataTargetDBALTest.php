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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal;

use CPSIT\ImportExportCore\Dbal\ConnectionRegistry;
use CPSIT\ImportExportCore\Dbal\DataTargetDBAL;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTargetDBALTest extends TestCase
{
    private Connection $connection;

    private DataTargetDBAL $subject;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE pages (uid INTEGER PRIMARY KEY AUTOINCREMENT, import_source TEXT, import_id TEXT, title TEXT, hidden INTEGER)'
        );
        $this->connection->executeStatement('CREATE TABLE pages_mm (uid_local INTEGER, uid_foreign INTEGER)');
        $this->subject = new DataTargetDBAL(new ConnectionRegistry(['default' => $this->connection]));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function configurationProvider(): array
    {
        return [
            'table only' => [['table' => 'pages'], true],
            'all options' => [['table' => 'main.pages', 'connection' => 'target', 'key' => ['import_source', 'import_id'], 'idField' => 'uid'], true],
            'no id field' => [['table' => 'pages_mm', 'idField' => ''], true],
            'missing table' => [[], false],
            'invalid table' => [['table' => 'pages p'], false],
            'connection not a string' => [['table' => 'pages', 'connection' => 1], false],
            'invalid id field' => [['table' => 'pages', 'idField' => 'u-id'], false],
            'key not a list' => [['table' => 'pages', 'key' => 'import_id'], false],
            'invalid key field' => [['table' => 'pages', 'key' => ['import id']], false],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('configurationProvider')]
    public function testValidatesConfiguration(array $configuration, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isConfigurationValid($configuration));
    }

    public function testInsertsRecordsAndReportsGeneratedIds(): void
    {
        $this->subject->setConfiguration(['table' => 'pages', 'idField' => 'uid']);
        $home = ['title' => 'Home', 'hidden' => false];
        $news = ['title' => 'News', 'hidden' => true];

        self::assertTrue($this->subject->persist($home));
        self::assertTrue($this->subject->persist($news));
        $this->subject->flushBatch(new TaskResult());

        self::assertSame('1', $this->subject->getTargetId($home));
        self::assertSame('2', $this->subject->getTargetId($news));
        self::assertSame(
            [['uid' => 1, 'title' => 'Home', 'hidden' => 0], ['uid' => 2, 'title' => 'News', 'hidden' => 1]],
            $this->connection->fetchAllAssociative('SELECT uid, title, hidden FROM pages ORDER BY uid')
        );
    }

    public function testReportsIdGivenInRecord(): void
    {
        $this->subject->setConfiguration(['table' => 'pages', 'idField' => 'uid']);
        $record = ['uid' => 10, 'title' => 'Home'];

        $this->subject->persist($record);

        self::assertSame('10', $this->subject->getTargetId($record));
    }

    public function testUpdatesExistingRowByKey(): void
    {
        $this->connection->insert('pages', ['import_source' => 'legacy', 'import_id' => '7', 'title' => 'Old']);
        $this->connection->insert('pages', ['import_source' => 'other', 'import_id' => '7', 'title' => 'Other']);
        $this->subject->setConfiguration(['table' => 'pages', 'key' => ['import_source', 'import_id'], 'idField' => 'uid']);
        $updated = ['import_source' => 'legacy', 'import_id' => '7', 'title' => 'New'];
        $inserted = ['import_source' => 'legacy', 'import_id' => '8', 'title' => 'Added'];

        self::assertFalse($this->subject->persist($updated));
        self::assertTrue($this->subject->persist($inserted));
        $this->subject->persistAll();

        self::assertSame('1', $this->subject->getTargetId($updated));
        self::assertSame('3', $this->subject->getTargetId($inserted));
        self::assertSame(
            ['New', 'Other', 'Added'],
            $this->connection->fetchFirstColumn('SELECT title FROM pages ORDER BY uid')
        );
    }

    public function testMatchesNullKeyValues(): void
    {
        $this->connection->insert('pages', ['import_source' => null, 'import_id' => '7', 'title' => 'Old']);
        $this->subject->setConfiguration(['table' => 'pages', 'key' => ['import_source', 'import_id']]);

        self::assertFalse($this->subject->persist(['import_source' => null, 'import_id' => '7', 'title' => 'New']));
        self::assertSame(['New'], $this->connection->fetchFirstColumn('SELECT title FROM pages'));
    }

    public function testUpdatesByKeyWithoutIdField(): void
    {
        $this->connection->insert('pages_mm', ['uid_local' => 1, 'uid_foreign' => 2]);
        $this->subject->setConfiguration(['table' => 'pages_mm', 'key' => ['uid_local'], 'idField' => '']);
        $record = ['uid_local' => 1, 'uid_foreign' => 3];

        self::assertFalse($this->subject->persist($record));
        self::assertTrue($this->subject->persist(['uid_local' => 2, 'uid_foreign' => 3]));
        $this->subject->persistAll();

        self::assertSame(
            [['uid_local' => 1, 'uid_foreign' => 3], ['uid_local' => 2, 'uid_foreign' => 3]],
            $this->connection->fetchAllAssociative('SELECT * FROM pages_mm ORDER BY uid_local')
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1794700028);

        $this->subject->getTargetId($record);
    }

    public function testCommitsEachBatch(): void
    {
        $this->subject->setConfiguration(['table' => 'pages', 'idField' => 'uid']);

        $this->subject->persist(['title' => 'Home']);
        self::assertTrue($this->connection->isTransactionActive());

        $this->subject->flushBatch(new TaskResult());
        self::assertFalse($this->connection->isTransactionActive());

        $this->subject->persist(['title' => 'News']);
        $this->connection->rollBack();

        self::assertSame(['Home'], $this->connection->fetchFirstColumn('SELECT title FROM pages'));
    }

    public function testForgetsIdsOfPreviousBatch(): void
    {
        $this->subject->setConfiguration(['table' => 'pages', 'idField' => 'uid']);
        $home = ['title' => 'Home'];
        $this->subject->persist($home);
        $this->subject->flushBatch(new TaskResult());
        $this->subject->persist(['title' => 'News']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1794700024);

        $this->subject->getTargetId($home);
    }

    public function testUsesConfigurationPassedToPersist(): void
    {
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other->executeStatement('CREATE TABLE news (id INTEGER PRIMARY KEY, title TEXT)');
        $subject = new DataTargetDBAL(new ConnectionRegistry(['default' => $this->connection, 'target' => $other]));

        $subject->persist(['title' => 'News'], ['table' => 'news', 'connection' => 'target']);
        $subject->persistAll();

        self::assertSame([['id' => 1, 'title' => 'News']], $other->fetchAllAssociative('SELECT * FROM news'));
    }

    /**
     * @return array<string, array{mixed, array<string, mixed>, int}>
     */
    public static function invalidRecordProvider(): array
    {
        return [
            'object' => [new \stdClass(), ['table' => 'pages'], 1794700021],
            'invalid configuration' => [['title' => 'Home'], ['table' => ''], 1794700022],
            'invalid column' => [['title = 1; --' => 'Home'], ['table' => 'pages'], 1794700023],
            'list' => [['Home'], ['table' => 'pages'], 1794700023],
            'missing key field' => [['title' => 'Home'], ['table' => 'pages', 'key' => ['import_id']], 1794700025],
            'empty record' => [[], ['table' => 'pages'], 1794700026],
            'array value' => [['title' => ['Home']], ['table' => 'pages'], 1794700027],
        ];
    }

    /**
     * @param array<mixed>|object $record
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidRecordProvider')]
    public function testThrowsForInvalidRecords(array|object $record, array $configuration, int $code): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode($code);

        $this->subject->persist($record, $configuration);
    }
}
