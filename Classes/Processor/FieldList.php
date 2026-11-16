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

/**
 * Reads a list of field names from a configuration value: a list of strings
 * or a comma-separated string. Names are trimmed, empty names are dropped.
 *
 * @internal
 */
final class FieldList
{
    /**
     * @return list<string>|null the field names, or null if the value is invalid or contains no name
     */
    public static function parse(mixed $value): ?array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }
        if (!\is_array($value) || !array_is_list($value)) {
            return null;
        }
        $fields = [];
        foreach ($value as $field) {
            if (!\is_string($field)) {
                return null;
            }
            $field = trim($field);
            if ('' !== $field) {
                $fields[] = $field;
            }
        }

        return [] === $fields ? null : $fields;
    }
}
