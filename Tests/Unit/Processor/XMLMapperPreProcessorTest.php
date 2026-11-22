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
use CPSIT\ImportExportCore\Processor\XMLMapperPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from PreProcessor\XMLMapperTest of t3import_export
 */
final class XMLMapperPreProcessorTest extends TestCase
{
    /**
     * @param array<mixed> $fields
     * @param array<mixed> $record
     * @return array<mixed>
     */
    private static function process(array $fields, array $record): array
    {
        $subject = new XMLMapperPreProcessor();
        $subject->setConfiguration(['fields' => $fields]);

        return $subject->process($record);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'no fields' => [['foo' => 'bar', 'bar' => []]];
        yield 'fields string' => [['fields' => 'bar']];
        yield 'fields empty' => [['fields' => []]];
        yield 'unknown rule' => [['fields' => ['stuff' => '@something']]];
        yield 'rule false' => [['fields' => ['staticSub' => ['foo' => false]]]];
        yield 'rule false in children' => [['fields' => ['manyChildren' => ['children' => ['id' => false]]]]];
        yield 'empty map' => [['fields' => ['foo' => []]]];
        yield 'unknown rule in combination' => [['fields' => ['foo' => '@value|@foo']]];
        yield 'mapTo not a string' => [['fields' => ['foo' => ['mapTo' => ['bar']]]]];
        yield 'mapTo empty' => [['fields' => ['foo' => ['mapTo' => '']]]];
        yield 'separateRow key not true' => [['fields' => ['foo' => ['@separateRow' => 'yes']]]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $subject = new XMLMapperPreProcessor();

        self::assertFalse($subject->isConfigurationValid($configuration));
        $this->expectException(InvalidConfigurationException::class);
        $subject->setConfiguration($configuration);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validConfigurationProvider(): iterable
    {
        yield 'children' => [['fields' => ['manyChildren' => ['children' => ['id' => '@attribute']]]]];
        yield 'map' => [['fields' => ['single' => ['id' => '@attribute']], 'other' => true]];
        yield 'cdata' => [['fields' => ['element' => '@cdata']]];
        yield 'value and cdata' => [['fields' => ['element' => ['content' => '@value|@cdata']]]];
        yield 'separate row' => [['fields' => ['setting' => '@separateRow']]];
        yield 'separate row key' => [['fields' => ['setting' => ['@separateRow' => true]]]];
        yield 'mapTo' => [['fields' => ['setting' => ['mapTo' => 'setup']]]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('validConfigurationProvider')]
    public function testAcceptsValidConfiguration(array $configuration): void
    {
        self::assertTrue((new XMLMapperPreProcessor())->isConfigurationValid($configuration));
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>, array<mixed>}>
     */
    public static function processProvider(): iterable
    {
        yield 'attribute' => [
            ['id' => 123],
            ['id' => '@attribute'],
            ['@attribute' => ['id' => 123]],
        ];
        yield 'attribute added to existing attributes' => [
            ['id' => 1, 'type' => 'news', '@attribute' => ['lang' => 'en']],
            ['id' => '@attribute', 'type' => '@attribute'],
            ['@attribute' => ['lang' => 'en', 'id' => 1, 'type' => 'news']],
        ];
        yield 'separate row' => [
            ['setting' => ['foo']],
            ['setting' => '@separateRow'],
            ['setting' => ['foo', '@separateRow' => true]],
        ];
        yield 'separate row in map' => [
            ['setting' => ['foo']],
            ['setting' => ['@separateRow' => true]],
            ['setting' => ['foo', '@separateRow' => true]],
        ];
        yield 'separate row keeps a string' => [
            ['setting' => 'foo'],
            ['setting' => '@separateRow'],
            ['setting' => 'foo'],
        ];
        yield 'mapTo in array' => [
            ['setting' => ['foo']],
            ['setting' => ['mapTo' => 'setup']],
            ['setting' => ['foo', '@mapTo' => 'setup']],
        ];
        yield 'mapTo wraps a scalar' => [
            ['foo' => 1],
            ['foo' => ['mapTo' => 'bar']],
            ['foo' => ['@value' => 1, '@mapTo' => 'bar']],
        ];
        yield 'value' => [
            ['element' => ['content' => 'fooBar']],
            ['element' => ['content' => '@value']],
            ['element' => ['@value' => 'fooBar']],
        ];
        yield 'cdata' => [
            ['element' => 'fooBar'],
            ['element' => '@cdata'],
            ['element' => ['@value' => 'fooBar', '@cdata' => true]],
        ];
        yield 'cdata of parent value' => [
            ['@value' => 'fooBar'],
            ['element' => '@cdata'],
            ['@value' => 'fooBar', '@cdata' => true],
        ];
        yield 'value and cdata' => [
            ['element' => ['content' => 'fooBar']],
            ['element' => ['content' => '@value|@cdata']],
            ['element' => ['@value' => 'fooBar', '@cdata' => true]],
        ];
        yield 'cdata and value in other order' => [
            ['element' => ['content' => 'fooBar']],
            ['element' => ['content' => '@cdata|@value']],
            ['element' => ['@value' => 'fooBar', '@cdata' => true]],
        ];
        yield 'other combination has no effect' => [
            ['element' => ['content' => 'fooBar']],
            ['element' => ['content' => '@attribute|@cdata']],
            ['element' => ['content' => 'fooBar']],
        ];
        yield 'children with mapTo and value' => [
            ['element' => [['foo' => 'bar'], ['foo' => 'baz']]],
            ['element' => ['children' => ['mapTo' => 'item', 'foo' => '@value']]],
            [
                'element' => [
                    ['@mapTo' => 'item', '@value' => 'bar'],
                    ['@mapTo' => 'item', '@value' => 'baz'],
                ],
            ],
        ];
        yield 'missing fields are skipped' => [
            ['other' => 'x'],
            ['id' => '@attribute', 'content' => '@value', 'element' => '@cdata', 'setting' => '@separateRow', 'sub' => ['id' => '@attribute']],
            ['other' => 'x'],
        ];
        yield 'empty field is not wrapped' => [
            ['foo' => ''],
            ['foo' => ['mapTo' => 'bar']],
            ['foo' => ''],
        ];
    }

    /**
     * @param array<mixed> $record
     * @param array<mixed> $fields
     * @param array<mixed> $expected
     */
    #[DataProvider('processProvider')]
    public function testProcess(array $record, array $fields, array $expected): void
    {
        self::assertSame($expected, self::process($fields, $record));
    }
}
