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

namespace CPSIT\ImportExportCore\Expression;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Evaluates expressions with the Symfony ExpressionLanguage.
 *
 * Besides the built-in syntax, the functions `upper`, `lower`, `trim`, `date`
 * and `strtotime` are available. `upper`, `lower` and `trim` return '' for
 * null, `strtotime` returns false for null.
 *
 * - A missing array key (`record['missing']`) evaluates to null without a
 *   warning; use `record['missing'] ?? 'default'` for a default value.
 * - Errors during the evaluation, e.g. a property of an array
 *   (`record.title`) or a function with an argument of the wrong type, throw
 *   an `InvalidConfigurationException` (`ERROR_EVALUATION_FAILED`).
 * - Ranges (`1..10`) may have at most `$maximumRangeSize` elements
 *   (`ERROR_RANGE_TOO_LARGE`).
 */
final class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    public const int ERROR_INVALID_EXPRESSION = 1794172347;
    public const int ERROR_EVALUATION_FAILED = 1795400001;
    public const int ERROR_RANGE_TOO_LARGE = 1795400002;
    public const int DEFAULT_MAXIMUM_RANGE_SIZE = 10000;

    private const array PHP_FUNCTIONS = [
        'upper' => 'mb_strtoupper',
        'lower' => 'mb_strtolower',
        'trim' => 'trim',
        'date' => 'date',
        'strtotime' => 'strtotime',
    ];

    /**
     * Value of the functions for the argument null
     */
    private const array NULL_RESULTS = [
        'upper' => '',
        'lower' => '',
        'trim' => '',
        'strtotime' => false,
    ];

    private readonly ExpressionLanguage $expressionLanguage;

    public function __construct(
        ?ExpressionLanguage $expressionLanguage = null,
        private readonly int $maximumRangeSize = self::DEFAULT_MAXIMUM_RANGE_SIZE,
    ) {
        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
        foreach (self::PHP_FUNCTIONS as $name => $phpFunction) {
            $this->expressionLanguage->addFunction(self::createFunction($name, $phpFunction));
        }
    }

    /**
     * @return array<string, string> function name => PHP function
     */
    public static function getPhpFunctions(): array
    {
        return self::PHP_FUNCTIONS;
    }

    public function evaluate(string $expression, array $variables = []): mixed
    {
        $previousHandler = set_error_handler(
            static function (int $level, string $message, string $file = '', int $line = 0) use (&$previousHandler): bool {
                if (str_starts_with($message, 'Undefined array key')) {
                    return true;
                }

                return \is_callable($previousHandler) && (bool)$previousHandler($level, $message, $file, $line);
            },
            E_WARNING
        );
        try {
            $parsedExpression = $this->expressionLanguage->parse($expression, array_keys($variables));
            $nodes = $parsedExpression->getNodes();
            if (self::isRange($nodes)) {
                $nodes = new LimitedRangeNode($nodes, $this->maximumRangeSize);
                $parsedExpression = new ParsedExpression($expression, $nodes);
            }
            $this->limitRanges($nodes);

            return $this->expressionLanguage->evaluate($parsedExpression, $variables);
        } catch (InvalidConfigurationException $exception) {
            throw $exception;
        } catch (SyntaxError $error) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid expression "%s": %s', $expression, $error->getMessage()),
                self::ERROR_INVALID_EXPRESSION,
                $error
            );
        } catch (\Throwable $error) {
            throw new InvalidConfigurationException(
                \sprintf('The expression "%s" could not be evaluated: %s', $expression, $error->getMessage()),
                self::ERROR_EVALUATION_FAILED,
                $error
            );
        } finally {
            restore_error_handler();
        }
    }

    public function isTrue(string $expression, array $variables = []): bool
    {
        return (bool)$this->evaluate($expression, $variables);
    }

    private static function createFunction(string $name, string $phpFunction): ExpressionFunction
    {
        if (!\array_key_exists($name, self::NULL_RESULTS)) {
            return ExpressionFunction::fromPhp($phpFunction, $name);
        }
        $nullResult = self::NULL_RESULTS[$name];

        return new ExpressionFunction(
            $name,
            static fn (string ...$arguments): string => \sprintf(
                '(%s === null ? %s : \%s(%s))',
                $arguments[0] ?? 'null',
                var_export($nullResult, true),
                $phpFunction,
                implode(', ', $arguments)
            ),
            static fn (array $variables, mixed ...$arguments): mixed => ($arguments[0] ?? null) === null
                ? $nullResult
                : $phpFunction(...$arguments)
        );
    }

    /**
     * @phpstan-assert-if-true BinaryNode $node
     */
    private static function isRange(Node $node): bool
    {
        return $node instanceof BinaryNode && ($node->attributes['operator'] ?? null) === '..';
    }

    /**
     * Replaces ranges by ranges with a limited number of elements
     */
    private function limitRanges(Node $node): void
    {
        foreach ($node->nodes as $key => $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if (self::isRange($child)) {
                $child = new LimitedRangeNode($child, $this->maximumRangeSize);
                $node->nodes[$key] = $child;
            }
            $this->limitRanges($child);
        }
    }
}
