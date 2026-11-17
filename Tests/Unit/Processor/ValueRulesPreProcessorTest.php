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
use CPSIT\ImportExportCore\Processor\ValueRulesPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueRulesPreProcessorTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $record
     *
     * @return array<mixed>
     */
    private static function process(array $rules, array $record): array
    {
        $subject = new ValueRulesPreProcessor();
        $subject->setConfiguration(['rules' => $rules]);

        return $subject->process($record);
    }

    public function testFallsBackToTheFirstNonEmptyField(): void
    {
        $rules = [['rule' => 'fallback', 'field' => 'teaser', 'from' => ['abstract', 'description']]];

        // teaser // abstract, as the existing import (RenderContent)
        self::assertSame('Abstract', self::process($rules, ['teaser' => '', 'abstract' => 'Abstract'])['teaser']);
        self::assertSame('Text', self::process($rules, ['teaser' => null, 'abstract' => '', 'description' => 'Text'])['teaser']);
        self::assertSame('Abstract', self::process($rules, ['abstract' => 'Abstract'])['teaser']);
        self::assertSame('Teaser', self::process($rules, ['teaser' => 'Teaser', 'abstract' => 'Abstract'])['teaser']);
        // '0' and whitespace are values, as in TypoScript
        self::assertSame('0', self::process($rules, ['teaser' => '0', 'abstract' => 'Abstract'])['teaser']);
        self::assertSame(' ', self::process($rules, ['teaser' => ' ', 'abstract' => 'Abstract'])['teaser']);
        // nothing to fall back to: unchanged
        self::assertSame(['teaser' => '', 'abstract' => null], self::process($rules, ['teaser' => '', 'abstract' => null]));
    }

    public function testSetsDefaultsForEmptyFields(): void
    {
        $rules = [['rule' => 'default', 'field' => 'fe_group', 'value' => 0]];

        self::assertSame(['fe_group' => 0], self::process($rules, []));
        self::assertSame(['fe_group' => 0], self::process($rules, ['fe_group' => '']));
        self::assertSame(['fe_group' => '1'], self::process($rules, ['fe_group' => '1']));
    }

    public function testCopiesAndRenamesFields(): void
    {
        self::assertSame(
            ['uid' => 7, 'import_id' => 7],
            self::process([['rule' => 'copy', 'field' => 'import_id', 'from' => 'uid']], ['uid' => 7])
        );
        self::assertSame(
            ['title' => 'A', 'import_id' => 7],
            self::process([['rule' => 'rename', 'field' => 'import_id', 'from' => 'uid']], ['uid' => 7, 'title' => 'A'])
        );
        self::assertSame(
            ['import_id' => null],
            self::process([['rule' => 'rename', 'field' => 'import_id', 'from' => 'uid']], [])
        );
    }

    public function testMapsValues(): void
    {
        $map = ['rule' => 'map', 'field' => 'type', 'values' => ['Tx_IhkofEvents_Course' => 'Tx_IhkEvents_Course', '1' => 'one']];

        self::assertSame(['type' => 'Tx_IhkEvents_Course'], self::process([$map], ['type' => 'Tx_IhkofEvents_Course']));
        self::assertSame(['type' => 'one'], self::process([$map], ['type' => 1]));
        // unmapped values are kept, or replaced by `otherwise`
        self::assertSame(['type' => 'Other'], self::process([$map], ['type' => 'Other']));
        self::assertSame(['type' => null], self::process([$map + ['otherwise' => null]], ['type' => 'Other']));
    }

    public function testRemovesTrimsAndJoins(): void
    {
        $rules = [
            ['rule' => 'trim', 'field' => 'title'],
            ['rule' => 'join', 'field' => 'name', 'from' => ['first', 'middle', 'last']],
            ['rule' => 'join', 'field' => 'path', 'from' => ['first', 'last'], 'separator' => '/'],
            ['rule' => 'remove', 'fields' => ['first', 'middle', 'last']],
        ];

        self::assertSame(
            ['title' => 'Title', 'name' => 'Ada Lovelace', 'path' => 'Ada/Lovelace'],
            self::process($rules, ['title' => "  Title\n", 'first' => 'Ada', 'middle' => '', 'last' => 'Lovelace'])
        );
    }

    public function testRemovesNestedFields(): void
    {
        $rules = [['rule' => 'remove', 'fields' => ['address.street', 'children.*.hidden', 'tags.*', 'missing.a', 'title.x', 'a.b']]];
        $record = [
            'title' => 'Title',
            'address' => ['street' => 'Main St', 'city' => 'Town'],
            'children' => [['uid' => 1, 'hidden' => 0], ['uid' => 2], 'text', ['uid' => 3, 'hidden' => 1]],
            'tags' => ['a', 'b'],
            'a.b' => 'literal',
            'a' => ['b' => 'kept'],
        ];

        self::assertSame(
            [
                'title' => 'Title',
                'address' => ['city' => 'Town'],
                'children' => [['uid' => 1], ['uid' => 2], 'text', ['uid' => 3]],
                'tags' => [],
                'a' => ['b' => 'kept'],
            ],
            self::process($rules, $record)
        );
    }

    public function testWrapsNonEmptyValues(): void
    {
        $rules = [
            ['rule' => 'wrap', 'field' => 'title', 'prefix' => '<h1>', 'suffix' => '</h1>'],
            ['rule' => 'wrap', 'field' => 'count', 'suffix' => ' items'],
            ['rule' => 'wrap', 'field' => 'zero', 'prefix' => '#'],
            ['rule' => 'wrap', 'field' => 'empty', 'prefix' => '#'],
            ['rule' => 'wrap', 'field' => 'null', 'prefix' => '#'],
            ['rule' => 'wrap', 'field' => 'list', 'prefix' => '#'],
            ['rule' => 'wrap', 'field' => 'missing', 'prefix' => '#'],
            ['rule' => 'wrap', 'field' => 'plain'],
        ];
        $record = ['title' => 'Title', 'count' => 5, 'zero' => '0', 'empty' => '', 'null' => null, 'list' => ['a'], 'plain' => 'x'];

        self::assertSame(
            ['title' => '<h1>Title</h1>', 'count' => '5 items', 'zero' => '#0', 'empty' => '', 'null' => null, 'list' => ['a'], 'plain' => 'x'],
            self::process($rules, $record)
        );
    }

    /**
     * ConcatenateFields of t3import_export: wrap each field, then append them to the target field
     */
    public function testWrapAndJoinReplaceConcatenateFields(): void
    {
        $rules = [
            ['rule' => 'wrap', 'field' => 'street', 'suffix' => '<br>'],
            ['rule' => 'wrap', 'field' => 'city', 'prefix' => ' '],
            ['rule' => 'join', 'field' => 'address', 'from' => ['address', 'street', 'zip', 'city'], 'separator' => ''],
        ];

        self::assertSame(
            ['address' => 'c/o A<br>Main St<br>12345 Town', 'street' => 'Main St<br>', 'zip' => '12345', 'city' => ' Town'],
            self::process($rules, ['address' => 'c/o A<br>', 'street' => 'Main St', 'zip' => '12345', 'city' => 'Town'])
        );
    }

    public function testAppliesRulesInOrderAndOnlyIfTheConditionHolds(): void
    {
        $rules = [
            ['rule' => 'default', 'field' => 'pid', 'value' => 10, 'when' => ['field' => 'type', 'equals' => 'course']],
            ['rule' => 'default', 'field' => 'pid', 'value' => 20, 'when' => ['field' => 'type', 'in' => ['seminar', 2]]],
            ['rule' => 'default', 'field' => 'pid', 'value' => 30, 'when' => ['field' => 'type', 'empty' => true]],
            ['rule' => 'copy', 'field' => 'label', 'from' => 'type', 'when' => ['field' => 'type', 'empty' => false]],
        ];

        self::assertSame(['type' => 'course', 'pid' => 10, 'label' => 'course'], self::process($rules, ['type' => 'course']));
        self::assertSame(['type' => '2', 'pid' => 20, 'label' => '2'], self::process($rules, ['type' => '2']));
        self::assertSame(['pid' => 30], self::process($rules, []));
        self::assertSame(['type' => 'other', 'label' => 'other'], self::process($rules, ['type' => 'other']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidRulesProvider(): iterable
    {
        yield 'no rules' => [[]];
        yield 'not a list' => [['a' => ['rule' => 'trim', 'field' => 'a']]];
        yield 'unknown rule' => [[['rule' => 'eval', 'field' => 'a']]];
        yield 'missing option' => [[['rule' => 'fallback', 'field' => 'a']]];
        yield 'unknown option' => [[['rule' => 'trim', 'field' => 'a', 'to' => 'b']]];
        yield 'empty field' => [[['rule' => 'trim', 'field' => '']]];
        yield 'from not a list' => [[['rule' => 'fallback', 'field' => 'a', 'from' => 'b']]];
        yield 'array value' => [[['rule' => 'default', 'field' => 'a', 'value' => []]]];
        yield 'condition with two tests' => [[['rule' => 'trim', 'field' => 'a', 'when' => ['field' => 'b', 'empty' => true, 'equals' => 1]]]];
        yield 'wrap prefix not a string' => [[['rule' => 'wrap', 'field' => 'a', 'prefix' => 1]]];
        yield 'wrap without field' => [[['rule' => 'wrap', 'prefix' => '<']]];
        yield 'condition without test' => [[['rule' => 'trim', 'field' => 'a', 'when' => ['field' => 'b']]]];
    }

    #[DataProvider('invalidRulesProvider')]
    public function testRejectsInvalidRules(mixed $rules): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ValueRulesPreProcessor())->setConfiguration(['rules' => $rules]);
    }
}
