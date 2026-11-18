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
 * Cleans string fields with PHP string functions. The options are applied in
 * the order of OPTIONS, independent of their order in the configuration.
 * Values that are no string and fields that are missing are kept.
 *
 * Moved from Clean of t3import_export.
 *
 * Configuration:
 *
 * - `fields` (map of field => options, required, not empty). Options:
 *   - `str_replace`: `{search: string, replace: string}`
 *   - `stripslashes`, `strip_emptytags` (removes empty HTML elements),
 *     `strip_tags`, `htmlspecialchars`, `strip_spaces` (removes all
 *     whitespace), `trim`, `ltrim`, `rtrim`, `strtolower`, `strtoupper`:
 *     enabled unless the value is false
 */
final class CleanPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public const array OPTIONS = [
        'str_replace',
        'stripslashes',
        'strip_emptytags',
        'strip_tags',
        'htmlspecialchars',
        'strip_spaces',
        'trim',
        'ltrim',
        'rtrim',
        'strtolower',
        'strtoupper',
    ];

    private const string EMPTY_TAG_PATTERN = '/<(\w+)\b(?:\s+[\w\-.:]+(?:\s*=\s*(?:"[^"]*"|"[^"]*"|[\w\-.:]+))?)*\s*\/?>\s*<\/\1\s*>/';

    public function isConfigurationValid(array $configuration): bool
    {
        $fields = $configuration['fields'] ?? null;
        if (!\is_array($fields) || [] === $fields || array_is_list($fields)) {
            return false;
        }
        foreach ($fields as $options) {
            if (!\is_array($options) || [] === $options || [] !== array_diff(array_keys($options), self::OPTIONS)) {
                return false;
            }
            if (isset($options['str_replace']) && !self::isReplacementValid($options['str_replace'])) {
                return false;
            }
        }

        return true;
    }

    public function process(array $record): array
    {
        /** @var array<string, array<string, mixed>> $fields */
        $fields = $this->configuration['fields'];
        foreach ($fields as $field => $options) {
            if (isset($record[$field]) && \is_string($record[$field])) {
                $record[$field] = self::clean($record[$field], $options);
            }
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function clean(string $value, array $options): string
    {
        foreach (self::OPTIONS as $option) {
            if (!isset($options[$option]) || false === $options[$option]) {
                continue;
            }
            $value = match ($option) {
                'str_replace' => self::replace($value, $options[$option]),
                'stripslashes' => stripslashes($value),
                'strip_emptytags' => '' === trim($value) ? $value : (string)preg_replace(self::EMPTY_TAG_PATTERN, '', $value),
                'strip_tags' => strip_tags($value),
                'htmlspecialchars' => htmlspecialchars($value),
                'strip_spaces' => (string)preg_replace('/\s+/', '', $value),
                'trim' => trim($value),
                'ltrim' => ltrim($value),
                'rtrim' => rtrim($value),
                'strtolower' => strtolower($value),
                'strtoupper' => strtoupper($value),
            };
        }

        return $value;
    }

    private static function replace(string $value, mixed $replacement): string
    {
        /** @var array{search: string, replace: string} $replacement checked by isConfigurationValid() */
        return str_replace($replacement['search'], $replacement['replace'], $value);
    }

    private static function isReplacementValid(mixed $replacement): bool
    {
        return \is_array($replacement)
            && isset($replacement['search'], $replacement['replace'])
            && \is_string($replacement['search']) && '' !== $replacement['search']
            && \is_string($replacement['replace']);
    }
}
