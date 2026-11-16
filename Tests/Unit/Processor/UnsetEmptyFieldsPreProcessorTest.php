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
use CPSIT\ImportExportCore\Processor\UnsetEmptyFieldsPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from UnsetEmptyFieldsTest of t3import_export
 */
final class UnsetEmptyFieldsPreProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>, array<mixed>}>
     */
    public static function processProvider(): iterable
    {
        yield 'empty string' => [['foo' => ''], []];
        yield 'integer zero' => [['foo' => 0], []];
        yield 'float zero' => [['foo' => 0.0], []];
        yield 'string zero' => [['foo' => '0'], []];
        yield 'null' => [['foo' => null], []];
        yield 'false' => [['foo' => false], []];
        yield 'empty array' => [['foo' => []], []];
        yield 'value is kept' => [['foo' => 'bar'], ['foo' => 'bar']];
        yield 'whitespace is kept' => [['foo' => ' '], ['foo' => ' ']];
        yield 'missing field' => [['bar' => ''], ['bar' => '']];
        yield 'several fields' => [['foo' => '', 'baz' => 0, 'x' => ''], ['x' => '']];
    }

    /**
     * @param array<mixed> $record
     * @param array<mixed> $expected
     */
    #[DataProvider('processProvider')]
    public function testRemovesEmptyFields(array $record, array $expected): void
    {
        $subject = new UnsetEmptyFieldsPreProcessor();
        $subject->setConfiguration(['fields' => 'foo, baz']);

        self::assertSame($expected, $subject->process($record));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'fields empty' => [['fields' => '']];
        yield 'fields no list' => [['fields' => ['a' => 'b']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new UnsetEmptyFieldsPreProcessor())->setConfiguration($configuration);
    }
}
