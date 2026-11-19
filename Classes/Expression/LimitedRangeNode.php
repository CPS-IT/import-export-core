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
use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

/**
 * Range (`a..b`) with a limited number of elements. Replaces the range nodes
 * of parsed expressions in `ExpressionEvaluator`.
 *
 * @internal
 */
final class LimitedRangeNode extends Node
{
    public function __construct(private readonly BinaryNode $range, private readonly int $maximumSize)
    {
        parent::__construct(['left' => $range->nodes['left'], 'right' => $range->nodes['right']], $range->attributes);
    }

    public function compile(Compiler $compiler): void
    {
        $this->range->compile($compiler);
    }

    /**
     * @param array<string, mixed> $functions
     * @param array<string, mixed> $values
     * @return array<mixed>
     */
    public function evaluate(array $functions, array $values): array
    {
        $left = $this->nodes['left']->evaluate($functions, $values);
        $right = $this->nodes['right']->evaluate($functions, $values);
        if (is_numeric($left) && is_numeric($right) && abs((float)$right - (float)$left) >= $this->maximumSize) {
            throw new InvalidConfigurationException(
                \sprintf('The range %s..%s has more than %d elements.', $left, $right, $this->maximumSize),
                ExpressionEvaluator::ERROR_RANGE_TOO_LARGE
            );
        }

        return range($left, $right);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->range->toArray();
    }
}
