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

namespace CPSIT\ImportExportCore\Tests\Unit\Factory;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Factory\ComponentFactory;
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Factory\TransferSetFactory;
use CPSIT\ImportExportCore\Factory\TransferTaskFactory;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';
require_once __DIR__ . '/../Service/Fixtures/Components.php';

class TransferSetFactoryTest extends TestCase
{
    private const array TASKS = [
        'genre' => ['source' => [], 'target' => []],
        'event' => ['source' => [], 'target' => []],
    ];

    private TransferSetFactory $subject;

    protected function setUp(): void
    {
        $registry = new ComponentRegistry();
        $registry->setDefault(DataSourceInterface::class, IdentifiableSource::class);
        $registry->setDefault(DataTargetInterface::class, InMemoryTarget::class);
        $this->subject = new TransferSetFactory(new TransferTaskFactory(new ComponentFactory($registry)));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function taskListProvider(): array
    {
        return [
            'comma-separated string' => [' event, genre ,'],
            'list' => [['event', 'genre']],
        ];
    }

    #[DataProvider('taskListProvider')]
    public function testCreatesSetWithTasksInGivenOrder(mixed $tasks): void
    {
        $set = $this->subject->create('legacyData', [
            'label' => 'Legacy data',
            'description' => 'All legacy records',
            'tasks' => $tasks,
        ], self::TASKS);

        self::assertSame('legacyData', $set->getIdentifier());
        self::assertSame('Legacy data', $set->getLabel());
        self::assertSame('All legacy records', $set->getDescription());
        self::assertSame(['event', 'genre'], array_keys($set->getTasks()));
        self::assertSame('genre', $set->getTasks()['genre']->getIdentifier());
    }

    public function testCreatesEmptySetWithoutTasks(): void
    {
        self::assertSame([], $this->subject->create('empty', [], self::TASKS)->getTasks());
    }

    public function testThrowsForUndefinedTask(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794230031);
        $this->expectExceptionMessage('Set "legacyData" refers to task "missing"');

        $this->subject->create('legacyData', ['tasks' => 'genre,missing'], self::TASKS);
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function invalidTasksProvider(): array
    {
        return [
            'not a string or list' => [1, 1794230032],
            'identifier not a string' => [['genre', 1], 1794230033],
        ];
    }

    #[DataProvider('invalidTasksProvider')]
    public function testThrowsForInvalidTaskList(mixed $tasks, int $code): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode($code);

        $this->subject->create('legacyData', ['tasks' => $tasks], self::TASKS);
    }
}
