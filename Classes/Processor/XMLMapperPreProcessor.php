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
 * Marks fields of a record for writing as XML: as attribute, as text or
 * CDATA section of the parent element, under another element name, or as
 * separate row. The markers are keys starting with `@` (`@attribute`,
 * `@value`, `@cdata`, `@mapTo`, `@separateRow`), as read by the XML stream
 * target of t3import_export. Missing fields are skipped.
 *
 * Moved from PreProcessor\XMLMapper of t3import_export.
 *
 * Configuration:
 *
 * - `fields` (map of field => rule, required, not empty). Rules:
 *   - `'@attribute'`: the field is moved into `@attribute` of its parent
 *     (`{id: 1}` -> `{@attribute: {id: 1}}`)
 *   - `'@value'`: the field is moved into `@value` of its parent, i.e. it
 *     becomes the text of the parent element
 *   - `'@cdata'`: the field becomes `{@value: value, @cdata: true}`; if the
 *     field is missing and the parent has `@value`, the parent gets
 *     `@cdata: true`
 *   - `'@value|@cdata'`: `@value`, then `@cdata`; other combinations of
 *     rules separated by `|` are accepted, but have no effect
 *   - `'@separateRow'`: the field (an array) gets `@separateRow: true`
 *   - a map of field => rule: the rules apply inside the field. A field
 *     with a string or number becomes `{@value: value}` first. In the map,
 *     `mapTo: name` adds `@mapTo: name` (the element name in the XML),
 *     `'@separateRow': true` adds `@separateRow: true`, and
 *     `children: {field: rule}` applies the rules to every array in the
 *     field, e.g. to every element of a list
 */
final class XMLMapperPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public const string KEY_FIELDS = 'fields';
    public const string ATTRIBUTE = '@attribute';
    public const string VALUE = '@value';
    public const string CDATA = '@cdata';
    public const string MAP_TO = '@mapTo';
    public const string SEPARATE_ROW = '@separateRow';
    public const string CHILDREN = 'children';
    public const string KEY_MAP_TO = 'mapTo';

    private const array RULES = [self::ATTRIBUTE, self::VALUE, self::CDATA, self::SEPARATE_ROW];

    public function isConfigurationValid(array $configuration): bool
    {
        $fields = $configuration[self::KEY_FIELDS] ?? null;

        return \is_array($fields) && $fields !== [] && self::isMapValid($fields);
    }

    public function process(array $record): array
    {
        /** @var array<array-key, mixed> $fields checked by isConfigurationValid() */
        $fields = $this->configuration[self::KEY_FIELDS];

        return self::remap($record, $fields);
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function isMapValid(array $map): bool
    {
        if ($map === []) {
            return false;
        }
        foreach ($map as $key => $rule) {
            if (!self::isRuleValid((string)$key, $rule)) {
                return false;
            }
        }

        return true;
    }

    private static function isRuleValid(string $key, mixed $rule): bool
    {
        if ($key === self::KEY_MAP_TO) {
            return \is_string($rule) && $rule !== '';
        }
        if ($key === self::SEPARATE_ROW) {
            return $rule === true;
        }
        if (\is_array($rule)) {
            return self::isMapValid($rule);
        }
        if (!\is_string($rule)) {
            return false;
        }
        foreach (explode('|', $rule) as $part) {
            if (!\in_array($part, self::RULES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $map
     * @return array<array-key, mixed>
     */
    private static function remap(array $record, array $map): array
    {
        foreach ($map as $key => $rule) {
            if (\is_array($rule) && self::isFilled($record, $key)) {
                $value = $record[$key];
                if (!\is_array($value)) {
                    $value = [self::VALUE => $value];
                }
                $record[$key] = self::remap($value, $rule);
            }

            if ($key === self::CHILDREN && \is_array($rule)) {
                foreach ($record as $childKey => $child) {
                    if (\is_array($child)) {
                        $record[$childKey] = self::remap($child, $rule);
                    }
                }
            }

            $parts = \is_string($rule) ? explode('|', $rule) : [];
            if (\count($parts) > 1 && \in_array(self::CDATA, $parts, true) && \in_array(self::VALUE, $parts, true)) {
                $record = self::mapValue($record, $key);
                $record = self::mapCdata($record, $key);
            } elseif ($rule === self::ATTRIBUTE) {
                $record = self::mapAttribute($record, $key);
            } elseif ($rule === self::SEPARATE_ROW || $key === self::SEPARATE_ROW) {
                $record = self::mapSeparateRow($record, $key);
            } elseif ($key === self::KEY_MAP_TO) {
                $record[self::MAP_TO] = $rule;
            } elseif ($rule === self::VALUE) {
                $record = self::mapValue($record, $key);
            } elseif ($rule === self::CDATA) {
                $record = self::mapCdata($record, $key);
            }
        }

        return $record;
    }

    /**
     * Whether the field is set and not empty, see empty()
     *
     * @param array<array-key, mixed> $record
     */
    private static function isFilled(array $record, int|string $key): bool
    {
        return !empty($record[$key]);
    }

    /**
     * @param array<array-key, mixed> $record
     * @return array<array-key, mixed>
     */
    private static function mapSeparateRow(array $record, int|string $key): array
    {
        if ($key === self::SEPARATE_ROW) {
            $record[self::SEPARATE_ROW] = true;
        } elseif (isset($record[$key]) && \is_array($record[$key])) {
            $record[$key][self::SEPARATE_ROW] = true;
        }

        return $record;
    }

    /**
     * @param array<array-key, mixed> $record
     * @return array<array-key, mixed>
     */
    private static function mapValue(array $record, int|string $key): array
    {
        if (\array_key_exists($key, $record)) {
            $record[self::VALUE] = $record[$key];
            unset($record[$key]);
        }

        return $record;
    }

    /**
     * @param array<array-key, mixed> $record
     * @return array<array-key, mixed>
     */
    private static function mapCdata(array $record, int|string $key): array
    {
        if (isset($record[$key]) && !\is_array($record[$key])) {
            $record[$key] = [self::VALUE => $record[$key], self::CDATA => true];
        } elseif (isset($record[self::VALUE])) {
            $record[self::CDATA] = true;
        }

        return $record;
    }

    /**
     * @param array<array-key, mixed> $record
     * @return array<array-key, mixed>
     */
    private static function mapAttribute(array $record, int|string $key): array
    {
        if (isset($record[$key])) {
            $attributes = $record[self::ATTRIBUTE] ?? [];
            $attributes = \is_array($attributes) ? $attributes : [];
            $attributes[$key] = $record[$key];
            $record[self::ATTRIBUTE] = $attributes;
            unset($record[$key]);
        }

        return $record;
    }
}
