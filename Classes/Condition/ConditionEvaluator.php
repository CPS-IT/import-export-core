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
use CPSIT\ImportExportCore\Expression\ExpressionEvaluator;
use CPSIT\ImportExportCore\Expression\ExpressionEvaluatorInterface;

/**
 * Evaluates conditions as used by the `disable` option of components.
 *
 * A condition may be
 * - `true`, `1` or `'1'`: always met
 * - an array with `if.result.hasMessage`: comma separated message ids; met if
 *   the result has a message with one of them
 * - an array with `expression`: met if the expression is true. The expression
 *   gets the variables `record` and `result`, a read-only `ResultView` of the
 *   result (null without a result).
 *
 * Any other value is not met.
 */
final class ConditionEvaluator implements ConditionEvaluatorInterface
{
    public function __construct(
        private readonly ExpressionEvaluatorInterface $expressionEvaluator = new ExpressionEvaluator(),
    ) {
    }

    public function isMet(mixed $condition, array $record = [], ?ResultInterface $result = null): bool
    {
        if ($condition === true || $condition === 1 || $condition === '1') {
            return true;
        }
        if (!\is_array($condition)) {
            return false;
        }

        $messageIds = $condition['if']['result']['hasMessage'] ?? null;
        if ($result !== null && (\is_string($messageIds) || \is_int($messageIds))) {
            foreach (explode(',', (string)$messageIds) as $id) {
                $id = trim($id);
                if ($id !== '' && $result->hasMessageWithId((int)$id)) {
                    return true;
                }
            }
        }

        if (isset($condition['expression'])) {
            return $this->expressionEvaluator->isTrue(
                (string)$condition['expression'],
                ['record' => $record, 'result' => $result === null ? null : new ResultView($result)]
            );
        }

        return false;
    }
}
