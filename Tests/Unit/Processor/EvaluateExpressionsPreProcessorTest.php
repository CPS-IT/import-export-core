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
use CPSIT\ImportExportCore\Expression\ExpressionEvaluatorInterface;
use CPSIT\ImportExportCore\Processor\EvaluateExpressionsPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvaluateExpressionsPreProcessorTest extends TestCase
{
    /**
     * @param array<string, string> $fields
     */
    private function createSubject(array $fields): EvaluateExpressionsPreProcessor
    {
        $subject = new EvaluateExpressionsPreProcessor();
        $subject->setConfiguration(['fields' => $fields]);

        return $subject;
    }

    public function testSetsFieldsFromExpressions(): void
    {
        $subject = $this->createSubject([
            'teaser' => "record['teaser'] ?: record['abstract']",
            'title' => "upper(trim(record['title'])) ~ ' (' ~ date('Y', record['starttime']) ~ ')'",
            'new' => "record['missing'] ?? 'default'",
        ]);

        $record = $subject->process([
            'teaser' => '',
            'abstract' => 'from abstract',
            'title' => ' annual report ',
            'starttime' => mktime(12, 0, 0, 6, 1, 2026),
        ]);

        self::assertSame('from abstract', $record['teaser']);
        self::assertSame('ANNUAL REPORT (2026)', $record['title']);
        self::assertSame('default', $record['new']);
    }

    public function testEvaluatesFieldsInOrder(): void
    {
        $record = $this->createSubject(['first' => "'a'", 'second' => "record['first'] ~ 'b'"])->process([]);

        self::assertSame(['first' => 'a', 'second' => 'ab'], $record);
    }

    public function testThrowsExceptionForInvalidExpression(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->createSubject(['title' => "record['title'] ~"])->process(['title' => 'foo']);
    }

    public function testUsesGivenExpressionEvaluator(): void
    {
        $expressionEvaluator = $this->createMock(ExpressionEvaluatorInterface::class);
        $expressionEvaluator->expects(self::once())->method('evaluate')
            ->with('foo', ['record' => ['id' => 1]])
            ->willReturn('bar');
        $subject = new EvaluateExpressionsPreProcessor($expressionEvaluator);
        $subject->setConfiguration(['fields' => ['title' => 'foo']]);

        self::assertSame(['id' => 1, 'title' => 'bar'], $subject->process(['id' => 1]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'no fields' => [[]];
        yield 'empty fields' => [['fields' => []]];
        yield 'fields not an array' => [['fields' => 'title']];
        yield 'numeric field name' => [['fields' => ["record['title']"]]];
        yield 'expression not a string' => [['fields' => ['title' => ['foo']]]];
        yield 'empty expression' => [['fields' => ['title' => ' ']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new EvaluateExpressionsPreProcessor())->setConfiguration($configuration);
    }
}
