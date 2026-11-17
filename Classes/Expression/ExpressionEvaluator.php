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
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Evaluates expressions with the Symfony ExpressionLanguage.
 *
 * Besides the built-in syntax, the functions `upper`, `lower`, `trim`, `date`
 * and `strtotime` are available.
 */
final class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    public const int ERROR_INVALID_EXPRESSION = 1794172347;

    private const array PHP_FUNCTIONS = [
        'upper' => 'mb_strtoupper',
        'lower' => 'mb_strtolower',
        'trim' => 'trim',
        'date' => 'date',
        'strtotime' => 'strtotime',
    ];

    private readonly ExpressionLanguage $expressionLanguage;

    public function __construct(?ExpressionLanguage $expressionLanguage = null)
    {
        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
        foreach (self::PHP_FUNCTIONS as $name => $phpFunction) {
            $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp($phpFunction, $name));
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
        try {
            return $this->expressionLanguage->evaluate($expression, $variables);
        } catch (SyntaxError $error) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid expression "%s": %s', $expression, $error->getMessage()),
                self::ERROR_INVALID_EXPRESSION,
                $error
            );
        }
    }

    public function isTrue(string $expression, array $variables = []): bool
    {
        return (bool)$this->evaluate($expression, $variables);
    }
}
