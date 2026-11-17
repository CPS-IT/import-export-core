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
use CPSIT\ImportExportCore\Processor\ImplodeArrayPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from ImplodeArrayTest of t3import_export
 */
final class ImplodeArrayPreProcessorTest extends TestCase
{
    /**
     * @param array<string, mixed> $fields
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    private static function process(array $fields, array $record): array
    {
        $subject = new ImplodeArrayPreProcessor();
        $subject->setConfiguration(['fields' => $fields]);

        return $subject->process($record);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<mixed>, array<mixed>}>
     */
    public static function processProvider(): iterable
    {
        yield 'array with default separator' => [['foo' => []], ['foo' => ['a', 'b', 'c']], ['foo' => 'a,b,c']];
        yield 'array with wrap as separator' => [['foo' => ['wrap' => ' | ']], ['foo' => ['a', 'b']], ['foo' => 'a | b']];
        yield 'empty array' => [['foo' => ['wrap' => ';']], ['foo' => []], ['foo' => '']];
        yield 'keys are dropped' => [['foo' => ['wrap' => ';']], ['foo' => ['x' => 'a', 'y' => 'b']], ['foo' => 'a;b']];
        yield 'string value is kept' => [['foo' => ['wrap' => ';']], ['foo' => 'a'], ['foo' => 'a']];
        yield 'missing field' => [['foo' => ['wrap' => ';']], ['bar' => ['a']], ['bar' => ['a']]];
        yield 'child array with default separator' => [
            ['tags' => ['child' => 'tag']],
            ['tags' => ['tag' => ['something', 'anything']]],
            ['tags' => 'something,anything'],
        ];
        yield 'child array with wrap as separator' => [
            ['tags' => ['child' => 'tag', 'wrap' => ', ']],
            ['tags' => ['tag' => ['something', 'anything']]],
            ['tags' => 'something, anything'],
        ];
        yield 'single child string replaces the field' => [
            ['tags' => ['child' => 'tag', 'wrap' => ', ']],
            ['tags' => ['tag' => 'something']],
            ['tags' => 'something'],
        ];
        yield 'missing child is ignored' => [
            ['tags' => ['child' => 'tag']],
            ['tags' => ['other' => ['a']]],
            ['tags' => ['other' => ['a']]],
        ];
        yield 'child of a string field is ignored' => [['tags' => ['child' => 'tag']], ['tags' => 'a'], ['tags' => 'a']];
        yield 'missing field with child' => [['tags' => ['child' => 'tag']], ['bar' => 'a'], ['bar' => 'a']];
        yield 'several fields' => [
            ['foo' => ['wrap' => '-'], 'bar' => ['wrap' => '+']],
            ['foo' => ['a', 'b'], 'bar' => ['c', 'd'], 'baz' => ['e']],
            ['foo' => 'a-b', 'bar' => 'c+d', 'baz' => ['e']],
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<mixed> $record
     * @param array<mixed> $expected
     */
    #[DataProvider('processProvider')]
    public function testImplodesFields(array $fields, array $record, array $expected): void
    {
        self::assertSame($expected, self::process($fields, $record));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'fields empty' => [['fields' => []]];
        yield 'fields is a list' => [['fields' => ['foo']]];
        yield 'options are a string' => [['fields' => ['foo' => 'bar']]];
        yield 'unknown option' => [['fields' => ['foo' => ['glue' => ',']]]];
        yield 'child empty' => [['fields' => ['foo' => ['child' => '']]]];
        yield 'wrap no string' => [['fields' => ['foo' => ['wrap' => 1]]]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ImplodeArrayPreProcessor())->setConfiguration($configuration);
    }
}
