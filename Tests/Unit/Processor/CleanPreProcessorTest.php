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
use CPSIT\ImportExportCore\Processor\CleanPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from CleanTest of t3import_export
 */
final class CleanPreProcessorTest extends TestCase
{
    /**
     * @param array<string, mixed> $fields
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    private static function process(array $fields, array $record): array
    {
        $subject = new CleanPreProcessor();
        $subject->setConfiguration(['fields' => $fields]);

        return $subject->process($record);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function optionProvider(): iterable
    {
        yield 'str_replace' => [
            ['str_replace' => ['search' => 'http://', 'replace' => 'https://']],
            'see http://example.com and http://example.org',
            'see https://example.com and https://example.org',
        ];
        yield 'stripslashes' => [['stripslashes' => true], 'It\\\'s', 'It\'s'];
        yield 'strip_emptytags' => [
            ['strip_emptytags' => true],
            '<p>text</p><p></p><span class="x"> </span><br/>',
            '<p>text</p><br/>',
        ];
        yield 'strip_emptytags keeps whitespace-only strings' => [['strip_emptytags' => true], '   ', '   '];
        yield 'strip_tags' => [['strip_tags' => true], '<p>Hello <b>World</b></p>', 'Hello World'];
        yield 'htmlspecialchars' => [
            ['htmlspecialchars' => true],
            '<a href="x">Tom & Jerry\'s</a>',
            '&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&#039;s&lt;/a&gt;',
        ];
        yield 'strip_spaces removes all whitespace' => [['strip_spaces' => true], " a b\tc\nd ", 'abcd'];
        yield 'trim' => [['trim' => true], "  foo \n", 'foo'];
        yield 'ltrim' => [['ltrim' => true], '  foo  ', 'foo  '];
        yield 'rtrim' => [['rtrim' => true], '  foo  ', '  foo'];
        yield 'strtolower' => [['strtolower' => true], 'FoO', 'foo'];
        yield 'strtoupper' => [['strtoupper' => true], 'FoO', 'FOO'];
        yield 'any value but false enables an option' => [['trim' => '0'], ' foo ', 'foo'];
        yield 'false disables an option' => [['trim' => false, 'strtoupper' => true], ' foo ', ' FOO '];
        // fixed order: str_replace, stripslashes, strip_emptytags, strip_tags, htmlspecialchars,
        // strip_spaces, trim, ltrim, rtrim, strtolower, strtoupper
        yield 'str_replace before strip_tags' => [
            ['strip_tags' => true, 'str_replace' => ['search' => '[b]', 'replace' => '<b>']],
            '[b]bold',
            'bold',
        ];
        yield 'strip_emptytags before strip_tags' => [['strip_tags' => true, 'strip_emptytags' => true], '<p></p>text', 'text'];
        yield 'strip_tags before htmlspecialchars' => [['htmlspecialchars' => true, 'strip_tags' => true], '<b>a & b</b>', 'a &amp; b'];
        yield 'htmlspecialchars before strip_spaces' => [['strip_spaces' => true, 'htmlspecialchars' => true], 'a & b', 'a&amp;b'];
        yield 'strtolower before strtoupper' => [['strtoupper' => true, 'strtolower' => true], 'FoO', 'FOO'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('optionProvider')]
    public function testAppliesOptions(array $options, string $value, string $expected): void
    {
        self::assertSame(['foo' => $expected], self::process(['foo' => $options], ['foo' => $value]));
    }

    public function testCleansSeveralFields(): void
    {
        self::assertSame(
            ['foo' => 'a', 'bar' => 'b', 'baz' => ' c '],
            self::process(
                ['foo' => ['trim' => true], 'bar' => ['strip_tags' => true]],
                ['foo' => ' a ', 'bar' => '<b>b</b>', 'baz' => ' c ']
            )
        );
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function untouchedRecordProvider(): iterable
    {
        yield 'integer' => [['foo' => 5]];
        yield 'array' => [['foo' => [' a ']]];
        yield 'null' => [['foo' => null]];
        yield 'missing field' => [['bar' => ' a ']];
    }

    /**
     * @param array<mixed> $record
     */
    #[DataProvider('untouchedRecordProvider')]
    public function testKeepsValuesThatAreNoString(array $record): void
    {
        self::assertSame($record, self::process(['foo' => ['trim' => true, 'strtoupper' => true]], $record));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'fields empty' => [['fields' => []]];
        yield 'fields is a list' => [['fields' => [['trim' => true]]]];
        yield 'options are a string' => [['fields' => ['foo' => 'trim']]];
        yield 'options empty' => [['fields' => ['foo' => []]]];
        yield 'unknown option' => [['fields' => ['foo' => ['ucfirst' => true]]]];
        yield 'str_replace without replace' => [['fields' => ['foo' => ['str_replace' => ['search' => 'a']]]]];
        yield 'str_replace with empty search' => [['fields' => ['foo' => ['str_replace' => ['search' => '', 'replace' => 'b']]]]];
        yield 'str_replace with list' => [['fields' => ['foo' => ['str_replace' => ['search' => ['a'], 'replace' => 'b']]]]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new CleanPreProcessor())->setConfiguration($configuration);
    }
}
