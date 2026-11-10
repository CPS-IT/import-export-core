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
use CPSIT\ImportExportCore\Dbal\DataSourceDBAL;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataSourceDBALTest extends TestCase
{
    private Connection $connection;

    private DataSourceDBAL $subject;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE pages (uid INTEGER PRIMARY KEY, pid INTEGER, title TEXT, deleted INTEGER, nav_title TEXT)'
        );
        foreach ([
            [1, 0, 'Home', 0, null],
            [2, 1, 'News', 0, 'Latest'],
            [3, 1, 'Events', 1, null],
            [4, 2, 'Archive', 0, null],
            [5, 2, 'Imprint', 0, null],
        ] as [$uid, $pid, $title, $deleted, $navTitle]) {
            $this->connection->insert(
                'pages',
                ['uid' => $uid, 'pid' => $pid, 'title' => $title, 'deleted' => $deleted, 'nav_title' => $navTitle]
            );
        }
        $this->subject = new DataSourceDBAL(new ConnectionRegistry(['default' => $this->connection]));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function configurationProvider(): array
    {
        return [
            'table only' => [['table' => 'pages'], true],
            'qualified table' => [['table' => 'main.pages'], true],
            'all options' => [[
                'table' => 'pages',
                'connection' => 'legacy',
                'fields' => ['uid', 'title'],
                'where' => ['deleted' => 0, 'pid' => [1, 2], 'nav_title' => null],
                'orderBy' => ['title' => 'desc'],
                'limit' => 10,
            ], true],
            'cursor' => [['table' => 'pages', 'fields' => ['uid', 'title'], 'cursorField' => 'uid', 'pageSize' => 2], true],
            'missing table' => [[], false],
            'invalid table' => [['table' => 'pages; DROP TABLE pages'], false],
            'empty connection' => [['table' => 'pages', 'connection' => ''], false],
            'fields not a list' => [['table' => 'pages', 'fields' => ['a' => 'uid']], false],
            'invalid field' => [['table' => 'pages', 'fields' => ['uid', 'count(*)']], false],
            'invalid where field' => [['table' => 'pages', 'where' => ['uid = 1 OR 1' => 1]], false],
            'where value an object' => [['table' => 'pages', 'where' => ['uid' => new \stdClass()]], false],
            'where empty list' => [['table' => 'pages', 'where' => ['uid' => []]], false],
            'where nested list' => [['table' => 'pages', 'where' => ['uid' => [[1]]]], false],
            'invalid order direction' => [['table' => 'pages', 'orderBy' => ['uid' => 'up']], false],
            'order by not a map' => [['table' => 'pages', 'orderBy' => 'uid'], false],
            'limit zero' => [['table' => 'pages', 'limit' => 0], false],
            'page size a string' => [['table' => 'pages', 'pageSize' => '10'], false],
            'invalid cursor field' => [['table' => 'pages', 'cursorField' => 'uid desc'], false],
            'cursor field not in fields' => [['table' => 'pages', 'fields' => ['title'], 'cursorField' => 'uid'], false],
            'cursor field with order' => [['table' => 'pages', 'cursorField' => 'uid', 'orderBy' => ['title' => 'ASC']], false],
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

    public function testReadsAllRecords(): void
    {
        $records = iterator_to_array($this->subject->getRecords(['table' => 'pages']), false);

        self::assertCount(5, $records);
        self::assertSame(['uid' => 1, 'pid' => 0, 'title' => 'Home', 'deleted' => 0, 'nav_title' => null], $records[0]);
    }

    public function testReadsFromQualifiedTable(): void
    {
        self::assertCount(5, iterator_to_array($this->subject->getRecords(['table' => 'main.pages']), false));
    }

    public function testSelectsFieldsWithConditionsOrderAndLimit(): void
    {
        $records = iterator_to_array($this->subject->getRecords([
            'table' => 'pages',
            'fields' => ['uid', 'title'],
            'where' => ['deleted' => 0, 'pid' => [1, 2]],
            'orderBy' => ['title' => 'DESC'],
            'limit' => 2,
        ]), false);

        self::assertSame([['uid' => 2, 'title' => 'News'], ['uid' => 5, 'title' => 'Imprint']], $records);
    }

    public function testSupportsNullAndStringConditions(): void
    {
        $records = iterator_to_array($this->subject->getRecords([
            'table' => 'pages',
            'fields' => ['uid'],
            'where' => ['nav_title' => null, 'title' => ['Home', 'Archive']],
        ]), false);

        self::assertSame([['uid' => 1], ['uid' => 4]], $records);
    }

    public function testReadsPagesInCursorOrder(): void
    {
        $configuration = ['table' => 'pages', 'fields' => ['uid', 'title'], 'cursorField' => 'uid', 'pageSize' => 2];
        $uids = [];
        foreach ($this->subject->getRecords($configuration) as $record) {
            $uids[] = $record['uid'];
            self::assertSame((string)$record['uid'], $this->subject->getCursor());
        }

        self::assertSame([1, 2, 3, 4, 5], $uids);
        self::assertSame('5', $this->subject->getCursor());
    }

    public function testCursorRespectsConditionsAndLimit(): void
    {
        $records = iterator_to_array($this->subject->getRecords([
            'table' => 'pages',
            'fields' => ['uid'],
            'where' => ['deleted' => 0],
            'cursorField' => 'uid',
            'pageSize' => 2,
            'limit' => 3,
        ]), false);

        self::assertSame([['uid' => 1], ['uid' => 2], ['uid' => 4]], $records);
        self::assertSame('4', $this->subject->getCursor());
    }

    public function testResumesAfterCursor(): void
    {
        $this->subject->resumeFrom('3');

        $records = iterator_to_array(
            $this->subject->getRecords(['table' => 'pages', 'fields' => ['uid'], 'cursorField' => 'uid', 'pageSize' => 1]),
            false
        );

        self::assertSame([['uid' => 4], ['uid' => 5]], $records);
    }

    public function testKeepsResumeCursorIfNothingIsRead(): void
    {
        $this->subject->resumeFrom('5');

        $records = iterator_to_array($this->subject->getRecords(['table' => 'pages', 'cursorField' => 'uid']), false);

        self::assertSame([], $records);
        self::assertSame('5', $this->subject->getCursor());
    }

    public function testCursorIsEmptyWithoutCursorField(): void
    {
        iterator_to_array($this->subject->getRecords(['table' => 'pages']), false);

        self::assertSame('', $this->subject->getCursor());
    }

    public function testThrowsForEmptyCursorValue(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794700012);

        iterator_to_array($this->subject->getRecords(['table' => 'pages', 'cursorField' => 'nav_title']), false);
    }

    public function testUsesNamedConnection(): void
    {
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other->executeStatement('CREATE TABLE pages (uid INTEGER PRIMARY KEY)');
        $other->insert('pages', ['uid' => 42]);
        $subject = new DataSourceDBAL(new ConnectionRegistry(['default' => $this->connection, 'legacy' => $other]));

        $records = iterator_to_array($subject->getRecords(['table' => 'pages', 'connection' => 'legacy']), false);

        self::assertSame([['uid' => 42]], $records);
    }

    public function testThrowsForUnknownConnection(): void
    {
        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(1794700001);

        iterator_to_array($this->subject->getRecords(['table' => 'pages', 'connection' => 'legacy']), false);
    }

    public function testThrowsForInvalidConfiguration(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794700011);

        iterator_to_array($this->subject->getRecords(['table' => '1pages']), false);
    }
}
