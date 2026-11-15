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
use CPSIT\ImportExportCore\Dbal\Filter;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataSourceDBALFilterTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE event (uid INTEGER PRIMARY KEY, title TEXT, deleted INTEGER, archived TEXT)');
        $this->connection->executeStatement('CREATE TABLE performance (uid INTEGER PRIMARY KEY, event INTEGER, date INTEGER, deleted INTEGER)');
        $now = time();
        // 1: recent performance; 2: only old performances; 3: no performances;
        // 4: only a deleted recent performance; 5: deleted event
        foreach ([[1, 'A', 0, null], [2, 'B', 0, '2020-01-01'], [3, 'C', 0, null], [4, 'D', 0, null], [5, 'E', 1, null]] as [$uid, $title, $deleted, $archived]) {
            $this->connection->insert('event', ['uid' => $uid, 'title' => $title, 'deleted' => $deleted, 'archived' => $archived]);
        }
        foreach ([[10, 1, $now - 86400, 0], [11, 1, $now - 3 * 365 * 86400, 0], [12, 2, $now - 3 * 365 * 86400, 0], [13, 4, $now, 1], [14, 5, $now, 0]] as [$uid, $event, $date, $deleted]) {
            $this->connection->insert('performance', ['uid' => $uid, 'event' => $event, 'date' => $date, 'deleted' => $deleted]);
        }
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return list<int>
     */
    private function uids(array $filter): array
    {
        $source = new DataSourceDBAL(new ConnectionRegistry(['default' => $this->connection]));
        $configuration = ['table' => 'event', 'fields' => ['uid'], 'where' => ['deleted' => 0], 'filter' => $filter, 'cursorField' => 'uid', 'pageSize' => 2];
        $source->setConfiguration($configuration);
        $uids = [];
        foreach ($source->getRecords($configuration) as $record) {
            self::assertIsNumeric($record['uid']);
            $uids[] = (int)$record['uid'];
        }

        return $uids;
    }

    public function testSelectsEventsAsTheLegacyExportCutoff(): void
    {
        // uid IN (performances from today -2 years) OR uid NOT IN (any performance), both on non-deleted performances
        $performances = ['deleted' => ['field' => 'deleted', 'op' => '=', 'value' => 0]];
        $filter = ['any' => [
            ['exists' => ['table' => 'performance', 'match' => ['event' => 'uid'], 'filter' => ['all' => [
                $performances['deleted'],
                ['field' => 'date', 'op' => '>=', 'value' => ['date' => '-P2Y', 'from' => 'today']],
            ]]]],
            ['notExists' => ['table' => 'performance', 'match' => ['event' => 'uid'], 'filter' => $performances['deleted']]],
        ]];

        self::assertSame([1, 3, 4], $this->uids($filter));
    }

    public function testComparesAndChecksForNull(): void
    {
        self::assertSame([2, 3, 4], $this->uids(['field' => 'uid', 'op' => '>', 'value' => 1]));
        self::assertSame([1, 4], $this->uids(['field' => 'title', 'op' => 'in', 'value' => ['A', 'D']]));
        self::assertSame([2, 3], $this->uids(['field' => 'uid', 'op' => 'notIn', 'value' => [1, 4]]));
        self::assertSame([2], $this->uids(['field' => 'archived', 'op' => 'isNotNull']));
        self::assertSame([1, 3, 4], $this->uids(['field' => 'archived', 'op' => 'isNull']));
        self::assertSame([2], $this->uids(['field' => 'archived', 'op' => '<', 'value' => ['date' => 'P0D', 'format' => 'date']]));
        self::assertSame([1, 2], $this->uids(['not' => ['field' => 'uid', 'op' => '>=', 'value' => 3]]));
    }

    public function testValuesArePassedAsParameters(): void
    {
        self::assertSame([], $this->uids(['field' => 'title', 'op' => '=', 'value' => "A' OR '1'='1"]));
    }

    public function testComputesRelativeDatesFromTheRunStart(): void
    {
        $query = $this->connection->createQueryBuilder()->select('uid')->from('event');
        $now = new \DateTimeImmutable('2026-11-14 15:30:00', new \DateTimeZone('Europe/Berlin'));
        Filter::apply($query, $this->connection, 'event', ['all' => [
            ['field' => 'a', 'op' => '>=', 'value' => ['date' => '-P2Y', 'from' => 'today']],
            ['field' => 'b', 'op' => '<', 'value' => ['date' => 'PT1H', 'format' => 'datetime']],
            ['field' => 'c', 'op' => '=', 'value' => ['date' => '-P1M', 'format' => 'date']],
        ]], $now);

        self::assertSame(
            [(new \DateTimeImmutable('2024-11-14 00:00:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), '2026-11-14 16:30:00', '2026-10-14'],
            array_values($query->getParameters())
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidFilterProvider(): iterable
    {
        yield 'SQL as field' => [['field' => 'uid = 1 OR 1', 'op' => '=', 'value' => 1]];
        yield 'unknown operator' => [['field' => 'uid', 'op' => 'LIKE', 'value' => '%']];
        yield 'list for =' => [['field' => 'uid', 'op' => '=', 'value' => [1]]];
        yield 'empty in' => [['field' => 'uid', 'op' => 'in', 'value' => []]];
        yield 'null check with value' => [['field' => 'uid', 'op' => 'isNull', 'value' => 1]];
        yield 'empty group' => [['all' => []]];
        yield 'two kinds' => [['all' => [['field' => 'uid', 'op' => 'isNull']], 'not' => ['field' => 'uid', 'op' => 'isNull']]];
        yield 'invalid duration' => [['field' => 'date', 'op' => '>', 'value' => ['date' => 'two years ago']]];
        yield 'unknown date base' => [['field' => 'date', 'op' => '>', 'value' => ['date' => 'P1D', 'from' => 'yesterday']]];
        yield 'exists without match' => [['exists' => ['table' => 'performance', 'match' => []]]];
        yield 'exists with SQL table' => [['exists' => ['table' => 'performance p, be_users', 'match' => ['event' => 'uid']]]];
        $deep = ['field' => 'uid', 'op' => 'isNull'];
        for ($level = 0; $level < Filter::MAX_DEPTH; ++$level) {
            $deep = ['not' => $deep];
        }
        yield 'too deep' => [$deep];
    }

    public function testRelativeDatesUseTheConfiguredTimeZone(): void
    {
        $this->connection->executeStatement('CREATE TABLE day (uid INTEGER PRIMARY KEY, date TEXT)');
        $this->connection->insert('day', ['uid' => 1, 'date' => '2026-11-14']);
        $this->connection->insert('day', ['uid' => 2, 'date' => '2026-11-15']);
        // 23:30 UTC is already the next day in Berlin
        $source = new DataSourceDBAL(
            new ConnectionRegistry(['default' => $this->connection]),
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-11-14 23:30:00', new \DateTimeZone('UTC'))
        );
        $uids = function (array $configuration) use ($source): array {
            $configuration += ['table' => 'day', 'fields' => ['uid'], 'filter' => ['field' => 'date', 'op' => '>=', 'value' => ['date' => 'P0D', 'from' => 'today', 'format' => 'date']]];
            $source->setConfiguration($configuration);

            return array_map(static fn (array $record): mixed => $record['uid'], iterator_to_array($source->getRecords($configuration), false));
        };

        self::assertSame([2], $uids(['timezone' => 'Europe/Berlin']));
        self::assertSame([1, 2], $uids(['timezone' => 'UTC']));
        // alias names such as Etc/UTC (common in container images) are accepted
        self::assertSame([1, 2], $uids(['timezone' => 'Etc/UTC']));
    }

    public function testRejectsUnknownTimeZones(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new DataSourceDBAL(new ConnectionRegistry(['default' => $this->connection])))->setConfiguration(['table' => 'event', 'timezone' => 'Mars/Olympus']);
    }

    #[DataProvider('invalidFilterProvider')]
    public function testRejectsInvalidFilters(mixed $filter): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new DataSourceDBAL(new ConnectionRegistry(['default' => $this->connection])))->setConfiguration(['table' => 'event', 'filter' => $filter]);
    }
}
