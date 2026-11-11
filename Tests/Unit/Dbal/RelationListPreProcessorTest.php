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
use CPSIT\ImportExportCore\Dbal\RelationListPreProcessor;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\State\Component\ResolveReferencePreProcessor;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RelationListPreProcessorTest extends TestCase
{
    private ConnectionRegistry $connections;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE event_genre_mm (uid_local INTEGER, uid_foreign INTEGER, sorting INTEGER, fieldname TEXT)');
        foreach ([[1, 7, 2, 'genre'], [1, 5, 1, 'genre'], [1, 9, 3, 'other'], [2, 7, 1, 'genre']] as [$local, $foreign, $sorting, $fieldname]) {
            $connection->insert('event_genre_mm', ['uid_local' => $local, 'uid_foreign' => $foreign, 'sorting' => $sorting, 'fieldname' => $fieldname]);
        }
        $this->connections = new ConnectionRegistry(['legacy' => $connection]);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSubject(array $configuration = []): RelationListPreProcessor
    {
        $subject = new RelationListPreProcessor($this->connections);
        $subject->setConfiguration($configuration + ['connection' => 'legacy', 'table' => 'event_genre_mm', 'targetField' => 'genre']);

        return $subject;
    }

    public function testSetsRelatedIdsInOrder(): void
    {
        self::assertSame(['uid' => 1, 'genre' => ['5', '7', '9']], $this->createSubject()->process(['uid' => 1]));
        self::assertSame(['uid' => 3, 'genre' => []], $this->createSubject()->process(['uid' => 3]));
    }

    public function testAppliesConditionsAndSeparator(): void
    {
        $subject = $this->createSubject(['where' => ['fieldname' => 'genre'], 'separator' => ',', 'sortField' => '']);

        self::assertSame(['uid' => 1, 'genre' => '5,7'], $subject->process(['uid' => 1]));
        self::assertSame(['genre' => ''], $subject->process([]));
    }

    public function testUsesOtherFields(): void
    {
        $subject = $this->createSubject(['idField' => 'id', 'localField' => 'uid_foreign', 'foreignField' => 'uid_local']);

        self::assertSame(['id' => 7, 'genre' => ['2', '1']], $subject->process(['id' => 7]));
    }

    public function testResolvesIdsWithIdMap(): void
    {
        $idMap = new InMemoryIdMap();
        $idMap->set(new IdMapping('genre', '5', '50'));
        $idMap->set(new IdMapping('genre', '7', '70'));
        $resolve = new ResolveReferencePreProcessor($idMap);
        $resolve->setConfiguration(['field' => 'genre', 'namespace' => 'genre', 'separator' => ',']);

        $record = $resolve->process($this->createSubject(['where' => ['fieldname' => 'genre'], 'separator' => ','])->process(['uid' => 1]));

        self::assertSame('50,70', $record['genre']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'missing table' => [['targetField' => 'genre']];
        yield 'missing target field' => [['table' => 'mm']];
        yield 'invalid local field' => [['table' => 'mm', 'targetField' => 'genre', 'localField' => 'a b']];
        yield 'invalid sort field' => [['table' => 'mm', 'targetField' => 'genre', 'sortField' => 'x;']];
        yield 'invalid where' => [['table' => 'mm', 'targetField' => 'genre', 'where' => ['a b' => 1]]];
        yield 'empty separator' => [['table' => 'mm', 'targetField' => 'genre', 'separator' => '']];
        yield 'empty connection' => [['table' => 'mm', 'targetField' => 'genre', 'connection' => '']];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new RelationListPreProcessor($this->connections))->setConfiguration($configuration);
    }
}
