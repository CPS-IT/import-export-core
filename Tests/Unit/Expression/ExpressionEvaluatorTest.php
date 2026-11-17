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

namespace CPSIT\ImportExportCore\Tests\Unit\Expression;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Expression\ExpressionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $subject;

    protected function setUp(): void
    {
        $this->subject = new ExpressionEvaluator();
    }

    public function testEvaluateReturnsValueOfExpression(): void
    {
        $record = ['teaser' => '', 'abstract' => 'from abstract'];

        self::assertSame(
            'from abstract',
            $this->subject->evaluate("record['teaser'] ?: record['abstract']", ['record' => $record])
        );
    }

    public function testEvaluateReturnsDefaultForMissingKeyWithNullCoalescing(): void
    {
        self::assertSame('default', $this->subject->evaluate("record['missing'] ?? 'default'", ['record' => []]));
    }

    public function testEvaluateThrowsExceptionForInvalidExpression(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(ExpressionEvaluator::ERROR_INVALID_EXPRESSION);

        $this->subject->evaluate("record['type'] ==", ['record' => []]);
    }

    public function testEvaluateThrowsExceptionForUnknownVariable(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->subject->evaluate('unknown == 1');
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function isTrueProvider(): iterable
    {
        yield 'comparison true' => ["record['type'] == 'news'", ['type' => 'news'], true];
        yield 'comparison false' => ["record['type'] == 'news'", ['type' => 'event'], false];
        yield 'non-empty string' => ["record['title']", ['title' => 'foo'], true];
        yield 'empty string' => ["record['title']", ['title' => ''], false];
        yield 'string zero' => ["record['hidden']", ['hidden' => '0'], false];
        yield 'in list' => ["record['uid'] in [1, 2, 3]", ['uid' => 2], true];
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('isTrueProvider')]
    public function testIsTrueReturnsBooleanValueOfExpression(string $expression, array $record, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isTrue($expression, ['record' => $record]));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, mixed}>
     */
    public static function functionProvider(): iterable
    {
        yield 'upper (multibyte)' => ["upper(record['v'])", ['v' => 'straße'], 'STRASSE'];
        yield 'lower (multibyte)' => ["lower(record['v'])", ['v' => 'ÄRGER'], 'ärger'];
        yield 'trim' => ["trim(record['v'])", ['v' => ' x '], 'x'];
        yield 'date' => ["date('Y-m-d', record['v'])", ['v' => mktime(12, 0, 0, 6, 1, 2026)], '2026-06-01'];
        yield 'strtotime' => [
            "strtotime(record['v']) == record['ts']",
            ['v' => '2026-06-01 12:00:00', 'ts' => mktime(12, 0, 0, 6, 1, 2026)],
            true,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('functionProvider')]
    public function testEvaluateProvidesFunctions(string $expression, array $record, mixed $expected): void
    {
        self::assertSame($expected, $this->subject->evaluate($expression, ['record' => $record]));
    }

    public function testGetPhpFunctionsListsFunctions(): void
    {
        self::assertSame(
            ['upper', 'lower', 'trim', 'date', 'strtotime'],
            array_keys(ExpressionEvaluator::getPhpFunctions())
        );
    }
}
