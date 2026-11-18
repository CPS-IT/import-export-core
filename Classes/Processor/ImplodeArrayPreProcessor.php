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
 * Joins the values of an array field to a string, e.g. a list read from XML.
 * With `child`, the array in that key of the field is joined; a string in that
 * key replaces the field. Keys are dropped. Fields that are missing or contain
 * no array (or no `child`) are kept.
 *
 * Moved from ImplodeArray of t3import_export.
 *
 * Configuration:
 *
 * - `fields` (map of field => options, required, not empty). Options:
 *   - `child` (string): key of the array inside the field
 *   - `wrap` (string, default ','): separator
 */
final class ImplodeArrayPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    private const string DEFAULT_SEPARATOR = ',';

    public function isConfigurationValid(array $configuration): bool
    {
        $fields = $configuration['fields'] ?? null;
        if (!\is_array($fields) || [] === $fields || array_is_list($fields)) {
            return false;
        }
        foreach ($fields as $options) {
            if (!\is_array($options) || [] !== array_diff(array_keys($options), ['child', 'wrap'])) {
                return false;
            }
            if (isset($options['child']) && (!\is_string($options['child']) || '' === $options['child'])) {
                return false;
            }
            if (isset($options['wrap']) && !\is_string($options['wrap'])) {
                return false;
            }
        }

        return true;
    }

    public function process(array $record): array
    {
        /** @var array<string, array{child?: string, wrap?: string}> $fields */
        $fields = $this->configuration['fields'];
        foreach ($fields as $field => $options) {
            if (!isset($record[$field])) {
                continue;
            }
            $value = $record[$field];
            if (isset($options['child'])) {
                if (!\is_array($value) || !isset($value[$options['child']])) {
                    continue;
                }
                $value = $value[$options['child']];
                if (\is_string($value)) {
                    $record[$field] = $value;

                    continue;
                }
            }
            if (\is_array($value)) {
                $record[$field] = implode($options['wrap'] ?? self::DEFAULT_SEPARATOR, $value);
            }
        }

        return $record;
    }
}
