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

namespace CPSIT\ImportExportCore\File;

/**
 * The CSV options shared by source and target: `delimiter`, `enclosure`,
 * `escape` and `fields`.
 *
 * @internal
 */
final readonly class CsvFormat
{
    public const string KEY_DELIMITER = 'delimiter';
    public const string KEY_ENCLOSURE = 'enclosure';
    public const string KEY_ESCAPE = 'escape';
    public const string KEY_FIELDS = 'fields';

    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $escape = '\\',
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function isValid(array $configuration): bool
    {
        foreach ([self::KEY_DELIMITER, self::KEY_ENCLOSURE] as $key) {
            if (isset($configuration[$key]) && (!\is_string($configuration[$key]) || \strlen($configuration[$key]) !== 1)) {
                return false;
            }
        }
        if (isset($configuration[self::KEY_ESCAPE])
            && (!\is_string($configuration[self::KEY_ESCAPE]) || \strlen($configuration[self::KEY_ESCAPE]) > 1)
        ) {
            return false;
        }

        return !\array_key_exists(self::KEY_FIELDS, $configuration)
            || self::fields($configuration) !== null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromConfiguration(array $configuration): self
    {
        $delimiter = $configuration[self::KEY_DELIMITER] ?? ',';
        $enclosure = $configuration[self::KEY_ENCLOSURE] ?? '"';
        $escape = $configuration[self::KEY_ESCAPE] ?? '\\';

        return new self(
            \is_string($delimiter) ? $delimiter : ',',
            \is_string($enclosure) ? $enclosure : '"',
            \is_string($escape) ? $escape : '\\',
        );
    }

    /**
     * Returns the field names of the option `fields`, a list of strings or a
     * comma-separated string; null if the option is missing or invalid.
     *
     * @param array<string, mixed> $configuration
     * @return non-empty-list<string>|null
     */
    public static function fields(array $configuration): ?array
    {
        $fields = $configuration[self::KEY_FIELDS] ?? null;
        if (\is_string($fields)) {
            $fields = array_map(trim(...), explode(',', $fields));
        }
        if (!\is_array($fields) || $fields === [] || !array_is_list($fields)) {
            return null;
        }
        foreach ($fields as $field) {
            if (!\is_string($field) || $field === '') {
                return null;
            }
        }
        if (\count(array_unique($fields)) !== \count($fields)) {
            return null;
        }

        return $fields;
    }
}
