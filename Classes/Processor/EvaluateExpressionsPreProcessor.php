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

namespace CPSIT\ImportExportCore\Processor;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Expression\ExpressionEvaluator;
use CPSIT\ImportExportCore\Expression\ExpressionEvaluatorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Sets fields of the record from expressions (Symfony ExpressionLanguage).
 *
 * The fields are evaluated in the configured order. Each expression gets the
 * variable `record`, which contains the values of fields set before.
 *
 * Configuration:
 *
 * - `fields` (map of field => expression, required, not empty): the expressions
 */
final class EvaluateExpressionsPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    private readonly ExpressionEvaluatorInterface $expressionEvaluator;

    public function __construct(?ExpressionEvaluatorInterface $expressionEvaluator = null)
    {
        $this->expressionEvaluator = $expressionEvaluator ?? new ExpressionEvaluator();
    }

    public function isConfigurationValid(array $configuration): bool
    {
        $fields = $configuration['fields'] ?? null;
        if (!\is_array($fields) || $fields === []) {
            return false;
        }
        foreach ($fields as $field => $expression) {
            if (!\is_string($field) || !\is_string($expression) || trim($expression) === '') {
                return false;
            }
        }

        return true;
    }

    public function process(array $record): array
    {
        /** @var array<string, string> $fields */
        $fields = $this->configuration['fields'];
        foreach ($fields as $field => $expression) {
            $record[$field] = $this->expressionEvaluator->evaluate($expression, ['record' => $record]);
        }

        return $record;
    }
}
