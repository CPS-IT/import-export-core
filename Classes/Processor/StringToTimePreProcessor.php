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
 * Converts date strings to Unix timestamps with `strtotime()` (PHP's default
 * time zone). Values that are no string or cannot be parsed, and fields that
 * are missing, become null.
 *
 * Moved from StringToTime of t3import_export.
 *
 * Configuration:
 *
 * - `fields` (list or comma-separated string, required): fields to convert
 * - `multipleRowFields` (list or comma-separated string): fields holding a
 *   list of child rows; the fields in `fields` are converted in each child row
 */
final class StringToTimePreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        if (null === FieldList::parse($configuration['fields'] ?? null)) {
            return false;
        }

        return !isset($configuration['multipleRowFields'])
            || null !== FieldList::parse($configuration['multipleRowFields']);
    }

    public function process(array $record): array
    {
        $fields = FieldList::parse($this->configuration['fields']) ?? [];
        $record = self::convert($record, $fields);

        $rowFields = isset($this->configuration['multipleRowFields'])
            ? FieldList::parse($this->configuration['multipleRowFields']) ?? []
            : [];
        foreach ($rowFields as $rowField) {
            if (!isset($record[$rowField]) || !\is_array($record[$rowField])) {
                continue;
            }
            foreach ($record[$rowField] as $key => $row) {
                if (\is_array($row)) {
                    $record[$rowField][$key] = self::convert($row, $fields);
                }
            }
        }

        return $record;
    }

    /**
     * @param array<mixed> $record
     * @param list<string> $fields
     *
     * @return array<mixed>
     */
    private static function convert(array $record, array $fields): array
    {
        foreach ($fields as $field) {
            $value = $record[$field] ?? null;
            $record[$field] = \is_string($value) ? (strtotime($value) ?: null) : null;
        }

        return $record;
    }
}
