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
use CPSIT\ImportExportCore\Processor\StringToTimePreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from StringToTimeTest of t3import_export
 */
final class StringToTimePreProcessorTest extends TestCase
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    private static function process(array $configuration, array $record): array
    {
        $subject = new StringToTimePreProcessor();
        $subject->setConfiguration($configuration);

        return $subject->process($record);
    }

    public function testConvertsFieldsAndSetsMissingFieldsToNull(): void
    {
        self::assertSame(
            ['foo' => strtotime('2026-01-15 10:00:00'), 'baz' => 'boo', 'bar' => null],
            self::process(['fields' => 'foo,bar'], ['foo' => '2026-01-15 10:00:00', 'baz' => 'boo'])
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nullValueProvider(): iterable
    {
        yield 'not parseable' => ['no date'];
        yield 'integer' => [1_700_000_000];
        yield 'array' => [['2026-01-15']];
        yield 'null' => [null];
    }

    #[DataProvider('nullValueProvider')]
    public function testSetsNullForValuesThatCannotBeConverted(mixed $value): void
    {
        self::assertSame(['foo' => null], self::process(['fields' => ['foo']], ['foo' => $value]));
    }

    public function testConvertsFieldsInChildRows(): void
    {
        self::assertSame(
            [
                'bar' => [
                    ['foo' => strtotime('2026-01-15')],
                    ['foo' => strtotime('2026-02-01'), 'x' => 1],
                ],
                'foo' => null,
            ],
            self::process(
                ['fields' => 'foo', 'multipleRowFields' => 'bar'],
                ['bar' => [['foo' => '2026-01-15'], ['foo' => '2026-02-01', 'x' => 1]]]
            )
        );
    }

    public function testSkipsChildRowFieldsWithoutRows(): void
    {
        self::assertSame(
            ['bar' => 'none', 'foo' => null],
            self::process(['fields' => 'foo', 'multipleRowFields' => 'bar, baz'], ['bar' => 'none'])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'fields empty' => [['fields' => '']];
        yield 'fields no list' => [['fields' => 1]];
        yield 'row fields invalid' => [['fields' => 'foo', 'multipleRowFields' => ['a' => 'b']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new StringToTimePreProcessor())->setConfiguration($configuration);
    }
}
