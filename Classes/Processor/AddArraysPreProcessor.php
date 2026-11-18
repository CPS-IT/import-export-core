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
 * Appends the values of array fields to a target field. Keys of the source
 * arrays are dropped, keys already in the target field are kept. Fields that
 * are missing or contain no array are skipped. A target value that is not an
 * array becomes the first element.
 *
 * Moved from AddArrays of t3import_export.
 *
 * Configuration:
 *
 * - `targetField` (string, required): name of the target field
 * - `fields` (list or comma-separated string, required): source fields
 */
final class AddArraysPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        $targetField = $configuration['targetField'] ?? null;

        return \is_string($targetField) && '' !== $targetField
            && null !== FieldList::parse($configuration['fields'] ?? null);
    }

    public function process(array $record): array
    {
        /** @var string $targetField */
        $targetField = $this->configuration['targetField'];
        foreach (FieldList::parse($this->configuration['fields']) ?? [] as $field) {
            if (!isset($record[$field]) || !\is_array($record[$field])) {
                continue;
            }
            if (isset($record[$targetField]) && !\is_array($record[$targetField])) {
                $record[$targetField] = [$record[$targetField]];
            }
            foreach ($record[$field] as $value) {
                $record[$targetField][] = $value;
            }
        }

        return $record;
    }
}
