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

namespace CPSIT\ImportExportCore\Condition;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Evaluates a configured condition, e.g. the `disable` option of a component.
 */
interface ConditionEvaluatorInterface
{
    /**
     * @param array<mixed> $record
     * @throws InvalidConfigurationException if the condition is invalid
     */
    public function isMet(mixed $condition, array $record = [], ?ResultInterface $result = null): bool;
}
