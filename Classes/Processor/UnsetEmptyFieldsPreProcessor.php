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
 * Removes fields whose value is empty in the sense of PHP's `empty()`:
 * null, '', '0', 0, 0.0, false and [].
 *
 * Moved from UnsetEmptyFields of t3import_export. Note that
 * `ValueRulesPreProcessor` considers only missing fields, null and '' empty.
 *
 * Configuration:
 *
 * - `fields`: fields to check, list or comma-separated string (required)
 */
final class UnsetEmptyFieldsPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        return null !== FieldList::parse($configuration['fields'] ?? null);
    }

    public function process(array $record): array
    {
        foreach (FieldList::parse($this->configuration['fields']) ?? [] as $field) {
            if (\array_key_exists($field, $record) && empty($record[$field])) {
                unset($record[$field]);
            }
        }

        return $record;
    }
}
