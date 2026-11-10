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
use CPSIT\ImportExportCore\State\Component\TargetIdPreProcessor;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\InMemoryIdMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TargetIdPreProcessorTest extends TestCase
{
    private InMemoryIdMap $idMap;

    protected function setUp(): void
    {
        $this->idMap = new InMemoryIdMap();
        $this->idMap->set(new IdMapping('genre', '7', '42'));
        $this->idMap->set(new IdMapping('audience', '8', '99'));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSubject(array $configuration = []): TargetIdPreProcessor
    {
        $subject = new TargetIdPreProcessor($this->idMap);
        $subject->setConfiguration($configuration + ['namespace' => 'genre', 'sourceIdField' => 'uid']);

        return $subject;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'namespace missing' => [['sourceIdField' => 'uid']];
        yield 'source ID field missing' => [['namespace' => 'genre']];
        yield 'namespace empty' => [['namespace' => '', 'sourceIdField' => 'uid']];
        yield 'target ID field empty' => [['namespace' => 'genre', 'sourceIdField' => 'uid', 'targetIdField' => '']];
        yield 'target ID field not a string' => [['namespace' => 'genre', 'sourceIdField' => 'uid', 'targetIdField' => 1]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new TargetIdPreProcessor($this->idMap))->setConfiguration($configuration);
    }

    public function testReplacesSourceIdWithTargetId(): void
    {
        self::assertSame(['uid' => '42', 'title' => 'Bildung'], $this->createSubject()->process(['uid' => 7, 'title' => 'Bildung']));
    }

    public function testRemovesSourceIdWithoutMapping(): void
    {
        self::assertSame(['title' => 'Neu'], $this->createSubject()->process(['uid' => 9, 'title' => 'Neu']));
    }

    public function testUsesNamespace(): void
    {
        self::assertSame(['title' => 'x'], $this->createSubject()->process(['uid' => 8, 'title' => 'x']));
        self::assertSame(['uid' => '99'], $this->createSubject(['namespace' => 'audience'])->process(['uid' => 8]));
    }

    public function testSetsSeparateTargetIdField(): void
    {
        $subject = $this->createSubject(['sourceIdField' => 'legacy_uid', 'targetIdField' => 'uid']);

        self::assertSame(['legacy_uid' => '7', 'uid' => '42'], $subject->process(['legacy_uid' => '7']));
        self::assertSame(['legacy_uid' => '9'], $subject->process(['legacy_uid' => '9', 'uid' => 3]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function missingSourceIdProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [[7]];
    }

    #[DataProvider('missingSourceIdProvider')]
    public function testRemovesTargetIdFieldWithoutSourceId(mixed $sourceId): void
    {
        $subject = $this->createSubject(['sourceIdField' => 'legacy_uid', 'targetIdField' => 'uid']);

        self::assertSame(['legacy_uid' => $sourceId], $subject->process(['legacy_uid' => $sourceId, 'uid' => 3]));
    }
}
