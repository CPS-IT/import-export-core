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
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Sets fields to constant values, e.g. a record type or a storage page.
 * Existing values are replaced.
 *
 * Configuration:
 *
 * - `values`: map of field => scalar or null (required, not empty)
 */
final class SetValuesPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        $values = $configuration['values'] ?? null;
        if (!\is_array($values) || $values === [] || array_is_list($values)) {
            return false;
        }
        foreach ($values as $field => $value) {
            if (!\is_string($field) || $field === '' || ($value !== null && !\is_scalar($value))) {
                return false;
            }
        }

        return true;
    }

    public function process(array $record): array
    {
        /** @var array<string, scalar|null> $values */
        $values = $this->configuration['values'];

        return array_replace($record, $values);
    }
}
