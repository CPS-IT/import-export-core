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

    public function testMissingArrayKeyEvaluatesToNullWithoutWarning(): void
    {
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            $value = $this->subject->evaluate("record['missing']", ['record' => []]);
        } finally {
            restore_error_handler();
        }

        self::assertNull($value);
        self::assertSame([], $warnings);
    }

    public function testOtherWarningsReachThePreviousErrorHandler(): void
    {
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            $this->subject->evaluate("'x' ~ record['list']", ['record' => ['list' => [1]]]);
            trigger_error('after the evaluation', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Array to string conversion', 'after the evaluation'], $warnings);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nullArgumentProvider(): iterable
    {
        yield 'upper' => ['upper(null)', ''];
        yield 'lower' => ['lower(null)', ''];
        yield 'trim' => ['trim(null)', ''];
        yield 'strtotime' => ['strtotime(null)', false];
    }

    #[DataProvider('nullArgumentProvider')]
    public function testFunctionsAcceptNull(string $expression, mixed $expected): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        });
        try {
            $value = $this->subject->evaluate($expression);
        } finally {
            restore_error_handler();
        }

        self::assertSame($expected, $value);
        self::assertSame([], $deprecations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function evaluationErrorProvider(): iterable
    {
        yield 'property of an array' => ['record.title'];
        yield 'method of null' => ['result.getInfo()'];
        yield 'argument of the wrong type' => ["upper(record['list'])"];
    }

    #[DataProvider('evaluationErrorProvider')]
    public function testEvaluationErrorsThrowInvalidConfigurationException(string $expression): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(ExpressionEvaluator::ERROR_EVALUATION_FAILED);

        $this->subject->evaluate($expression, ['record' => ['title' => 'x', 'list' => [1]], 'result' => null]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function largeRangeProvider(): iterable
    {
        yield 'range' => ['1..2000000'];
        yield 'range in a comparison' => ['5 in 1..2000000'];
        yield 'range from a variable' => ["1 in 0..record['n']"];
        yield 'nested' => ["record['n'] > 0 and (1 in (0..record['n']))"];
    }

    #[DataProvider('largeRangeProvider')]
    public function testRangesAreLimited(string $expression): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(ExpressionEvaluator::ERROR_RANGE_TOO_LARGE);

        $this->subject->evaluate($expression, ['record' => ['n' => 2000000]]);
    }

    public function testSmallRangesAreEvaluated(): void
    {
        self::assertSame([1, 2, 3], $this->subject->evaluate('1..3'));
        self::assertTrue($this->subject->isTrue("record['n'] in 1..10", ['record' => ['n' => 10]]));
        self::assertCount(5, (new ExpressionEvaluator(maximumRangeSize: 5))->evaluate('1..5'));
    }

    public function testMaximumRangeSizeIsConfigurable(): void
    {
        $this->expectExceptionCode(ExpressionEvaluator::ERROR_RANGE_TOO_LARGE);

        (new ExpressionEvaluator(maximumRangeSize: 5))->evaluate('1..6');
    }
}
