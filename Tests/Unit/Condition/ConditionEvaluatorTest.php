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

namespace CPSIT\ImportExportCore\Tests\Unit\Condition;

use CPSIT\ImportExportCore\Condition\ConditionEvaluator;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Expression\ExpressionEvaluatorInterface;
use CPSIT\ImportExportCore\Messaging\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $subject;

    protected function setUp(): void
    {
        $this->subject = new ConditionEvaluator();
    }

    private function createResultWithMessage(int $id): TaskResult
    {
        $result = new TaskResult();
        $result->addMessages([new Message('message', 'title', Message::SEVERITY_OK, $id)]);

        return $result;
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function scalarConditionProvider(): iterable
    {
        yield 'null' => [null, false];
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'integer 1' => [1, true];
        yield 'integer 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'other string' => ['foo', false];
        yield 'list' => [['foo'], false];
        yield 'empty array' => [[], false];
    }

    #[DataProvider('scalarConditionProvider')]
    public function testEvaluatesSimpleConditions(mixed $condition, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isMet($condition));
    }

    /**
     * @return iterable<string, array{int|string, bool}>
     */
    public static function messageConditionProvider(): iterable
    {
        yield 'single matching id' => ['12345', true];
        yield 'matching id in list' => ['3, 12345,7', true];
        yield 'integer id' => [12345, true];
        yield 'no matching id' => ['3,7', false];
    }

    #[DataProvider('messageConditionProvider')]
    public function testEvaluatesMessageIdsOfResult(int|string $messageIds, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->subject->isMet(
                ['if' => ['result' => ['hasMessage' => $messageIds]]],
                [],
                $this->createResultWithMessage(12345)
            )
        );
    }

    public function testMessageConditionIsNotMetWithoutResult(): void
    {
        self::assertFalse($this->subject->isMet(['if' => ['result' => ['hasMessage' => '12345']]]));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function expressionProvider(): iterable
    {
        yield 'matching record' => ["record['type'] == 'news'", ['type' => 'news'], true];
        yield 'not matching record' => ["record['type'] == 'news'", ['type' => 'event'], false];
        yield 'without result' => ['result != null', [], false];
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('expressionProvider')]
    public function testEvaluatesExpression(string $expression, array $record, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isMet(['expression' => $expression], $record));
    }

    public function testPassesResultToExpression(): void
    {
        self::assertTrue(
            $this->subject->isMet(['expression' => 'result.hasMessageWithId(5)'], [], $this->createResultWithMessage(5))
        );
    }

    public function testEvaluatesExpressionIfNoMessageMatches(): void
    {
        $condition = ['if' => ['result' => ['hasMessage' => '3']], 'expression' => "record['id'] == 1"];

        self::assertTrue($this->subject->isMet($condition, ['id' => 1], $this->createResultWithMessage(5)));
        self::assertFalse($this->subject->isMet($condition, ['id' => 2], $this->createResultWithMessage(5)));
    }

    public function testUsesGivenExpressionEvaluator(): void
    {
        $expressionEvaluator = $this->createMock(ExpressionEvaluatorInterface::class);
        $expressionEvaluator->expects(self::once())->method('isTrue')
            ->with('foo', ['record' => ['id' => 1], 'result' => null])
            ->willReturn(true);

        self::assertTrue((new ConditionEvaluator($expressionEvaluator))->isMet(['expression' => 'foo'], ['id' => 1]));
    }

    public function testThrowsExceptionForInvalidExpression(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->subject->isMet(['expression' => "record['id'] =="]);
    }
}
