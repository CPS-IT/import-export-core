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

namespace CPSIT\ImportExportCore\Tests\Unit\State\Component;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\State\Component\ResolveReferencePreProcessor;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResolveReferencePreProcessorTest extends TestCase
{
    private InMemoryIdMap $idMap;

    protected function setUp(): void
    {
        $this->idMap = new InMemoryIdMap();
        $this->idMap->set(new IdMapping('sys_category', '3', '30'));
        $this->idMap->set(new IdMapping('sys_category', '4', '40'));
        $this->idMap->set(new IdMapping('pages', '3', '99'));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSubject(array $configuration): ResolveReferencePreProcessor
    {
        $subject = new ResolveReferencePreProcessor($this->idMap);
        $subject->setConfiguration($configuration + ['field' => 'category', 'namespace' => 'sys_category']);

        return $subject;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'field missing' => [['namespace' => 'pages']];
        yield 'namespace missing' => [['field' => 'pid']];
        yield 'field empty' => [['field' => '', 'namespace' => 'pages']];
        yield 'unknown onMissing' => [['field' => 'pid', 'namespace' => 'pages', 'onMissing' => 'ignore']];
        yield 'empty separator' => [['field' => 'pid', 'namespace' => 'pages', 'separator' => '']];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ResolveReferencePreProcessor($this->idMap))->setConfiguration($configuration);
    }

    public function testReplacesSourceIdWithTargetIdOfNamespace(): void
    {
        $record = $this->createSubject([])->process(['uid' => 1, 'category' => 3]);

        self::assertSame(['uid' => 1, 'category' => '30'], $record);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'zero' => [0];
        yield 'zero string' => ['0'];
        yield 'empty list' => [[]];
    }

    #[DataProvider('emptyValueProvider')]
    public function testLeavesEmptyValuesUnchanged(mixed $value): void
    {
        self::assertSame(['category' => $value], $this->createSubject([])->process(['category' => $value]));
    }

    public function testLeavesRecordWithoutFieldUnchanged(): void
    {
        self::assertSame(['uid' => 1], $this->createSubject([])->process(['uid' => 1]));
    }

    public function testResolvesListOfIds(): void
    {
        $record = $this->createSubject([])->process(['category' => [3, '4']]);

        self::assertSame(['category' => ['30', '40']], $record);
    }

    public function testResolvesSeparatedIds(): void
    {
        $record = $this->createSubject(['separator' => ','])->process(['category' => '3, 4']);

        self::assertSame(['category' => '30,40'], $record);
    }

    public function testFailsForMissingMappingByDefault(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794600012);
        $this->expectExceptionMessage('No mapping for ID "5" of field "category" in namespace "sys_category".');

        $this->createSubject([])->process(['category' => 5]);
    }

    public function testKeepsSourceIdOfMissingMappingAndLogsWarning(): void
    {
        $subject = $this->createSubject(['onMissing' => 'keep', 'separator' => ',']);

        $record = $subject->process(['category' => '3,5']);

        self::assertSame(['category' => '30,5'], $record);
        $messages = $subject->getAndPurgeMessages();
        self::assertCount(1, $messages);
        self::assertSame(Message::SEVERITY_WARNING, $messages[0]->getSeverity());
        self::assertStringContainsString('The source ID is kept.', $messages[0]->getMessage());
    }

    public function testRemovesIdOfMissingMappingAndLogsWarning(): void
    {
        $subject = $this->createSubject(['onMissing' => 'empty']);

        self::assertSame(['category' => ['30']], $subject->process(['category' => [3, 5]]));
        self::assertSame(['category' => ''], $subject->process(['category' => 5]));
        self::assertCount(2, $subject->getAndPurgeMessages());
    }

    public function testThrowsForValuesThatAreNoIds(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794600011);

        $this->createSubject([])->process(['category' => [['nested']]]);
    }
}
