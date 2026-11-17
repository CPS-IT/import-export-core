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

namespace CPSIT\ImportExportCore\Tests\Unit\Processor;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Processor\AddArraysPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from AddArraysTest of t3import_export
 */
final class AddArraysPreProcessorTest extends TestCase
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    private static function process(array $configuration, array $record): array
    {
        $subject = new AddArraysPreProcessor();
        $subject->setConfiguration($configuration);

        return $subject->process($record);
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>}>
     */
    public static function processProvider(): iterable
    {
        yield 'empty record' => [[], []];
        yield 'no source fields' => [['foo' => 'fooValue'], ['foo' => 'fooValue']];
        yield 'source is no array' => [
            ['foo' => 'fooValue', 'bar' => 'barValue'],
            ['foo' => 'fooValue', 'bar' => 'barValue'],
        ];
        yield 'single value' => [
            ['foo' => [], 'bar' => ['firstBarValue']],
            ['foo' => ['firstBarValue'], 'bar' => ['firstBarValue']],
        ];
        yield 'multiple values' => [
            ['foo' => [], 'bar' => ['firstBarValue', 'secondBarValue']],
            ['foo' => ['firstBarValue', 'secondBarValue'], 'bar' => ['firstBarValue', 'secondBarValue']],
        ];
        yield 'duplicate values are kept' => [
            ['foo' => [0], 'bar' => [0, 1]],
            ['foo' => [0, 0, 1], 'bar' => [0, 1]],
        ];
        yield 'keys of the source are dropped' => [
            ['foo' => ['key1' => 'valueFoo'], 'bar' => ['key1' => 'valueBar']],
            ['foo' => ['key1' => 'valueFoo', 'valueBar'], 'bar' => ['key1' => 'valueBar']],
        ];
        yield 'missing target field is created' => [
            ['bar' => ['a'], 'baz' => ['b']],
            ['bar' => ['a'], 'baz' => ['b'], 'foo' => ['a', 'b']],
        ];
        yield 'target value that is no array becomes the first element' => [
            ['foo' => 'x', 'bar' => ['a']],
            ['foo' => ['x', 'a'], 'bar' => ['a']],
        ];
    }

    /**
     * @param array<mixed> $record
     * @param array<mixed> $expected
     */
    #[DataProvider('processProvider')]
    public function testAddsArrays(array $record, array $expected): void
    {
        self::assertSame($expected, self::process(['targetField' => 'foo', 'fields' => 'bar,baz'], $record));
    }

    public function testAcceptsListOfFields(): void
    {
        self::assertSame(
            ['bar' => ['a'], 'baz' => ['b'], 'foo' => ['a', 'b']],
            self::process(['targetField' => 'foo', 'fields' => ['bar', 'baz']], ['bar' => ['a'], 'baz' => ['b']])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'target field is no string' => [['targetField' => [], 'fields' => 'bar']];
        yield 'target field empty' => [['targetField' => '', 'fields' => 'bar']];
        yield 'fields missing' => [['targetField' => 'foo']];
        yield 'fields empty' => [['targetField' => 'foo', 'fields' => '']];
        yield 'fields is a map' => [['targetField' => 'foo', 'fields' => ['a' => 'b']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new AddArraysPreProcessor())->setConfiguration($configuration);
    }
}
