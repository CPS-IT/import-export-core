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

/**
 * Evaluates expressions in configuration, e.g. conditions or field values.
 */
interface ExpressionEvaluatorInterface
{
    /**
     * @param array<string, mixed> $variables
     * @throws InvalidConfigurationException if the expression is invalid
     */
    public function evaluate(string $expression, array $variables = []): mixed;

    /**
     * @param array<string, mixed> $variables
     * @throws InvalidConfigurationException if the expression is invalid
     */
    public function isTrue(string $expression, array $variables = []): bool;
}
