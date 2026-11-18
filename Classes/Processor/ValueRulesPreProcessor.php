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
 * Applies declarative rules to the field values of a record, in order.
 * Covers the TypoScript rules of t3import_export used in imports: fallback (`teaser // abstract`), default value, copy and
 * rename (MapFields), value mapping (MapFieldValues), remove (RemoveFields),
 * trim, wrap and join (ConcatenateFields). No expressions, so no code from a pipeline is executed.
 *
 * A value is empty if the field is missing, null or ''. Every rule may have a
 * condition `when`: `{field, equals}`, `{field, in}` or `{field, empty}`.
 *
 * Configuration:
 *
 * - `rules` (list of rules, required, not empty), each with `rule`:
 *   - `fallback`: `field` empty -> first non-empty value of the fields `from`
 *   - `default`: `field` empty -> `value`
 *   - `copy`: `field` = value of `from`
 *   - `rename`: `field` = value of `from`, `from` removed
 *   - `map`: `field` = `values[current]`; unmapped values are kept, or
 *     replaced by `otherwise` if given
 *   - `remove`: the fields `fields` are removed. A name with dots is a path
 *     into nested arrays, `*` stands for every element of a list:
 *     `address.street`, `children.*.hidden`. A field whose name is the
 *     whole path is removed instead
 *   - `trim`: whitespace around the string in `field` is removed
 *   - `wrap`: `field` not empty -> `prefix` . value . `suffix` (both default '')
 *   - `join`: `field` = non-empty values of the fields `from`, joined by
 *     `separator` (default ' ')
 *
 * @phpstan-type Condition array{field: string, equals?: scalar|null, in?: list<scalar|null>, empty?: bool}
 * @phpstan-type Rule array{rule: string, field?: string, from?: string|list<string>, value?: scalar|null, values?: array<scalar|null>, otherwise?: scalar|null, fields?: list<string>, separator?: string, prefix?: string, suffix?: string, when?: Condition}
 */
final class ValueRulesPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    private const array RULES = [
        'fallback' => ['field' => 'string', 'from' => 'list'],
        'default' => ['field' => 'string', 'value' => 'value'],
        'copy' => ['field' => 'string', 'from' => 'string'],
        'rename' => ['field' => 'string', 'from' => 'string'],
        'map' => ['field' => 'string', 'values' => 'map', '?otherwise' => 'value'],
        'remove' => ['fields' => 'list'],
        'trim' => ['field' => 'string'],
        'wrap' => ['field' => 'string', '?prefix' => 'any-string', '?suffix' => 'any-string'],
        'join' => ['field' => 'string', 'from' => 'list', '?separator' => 'any-string'],
    ];

    public function isConfigurationValid(array $configuration): bool
    {
        $rules = $configuration['rules'] ?? null;
        if (!\is_array($rules) || [] === $rules || !array_is_list($rules)) {
            return false;
        }
        foreach ($rules as $rule) {
            if (!\is_array($rule) || !self::isRuleValid($rule)) {
                return false;
            }
        }

        return true;
    }

    public function process(array $record): array
    {
        /** @var list<Rule> $rules checked by isConfigurationValid() */
        $rules = $this->configuration['rules'];
        foreach ($rules as $rule) {
            if (isset($rule['when']) && !self::matches($rule['when'], $record)) {
                continue;
            }
            $record = self::apply($rule, $record);
        }

        return $record;
    }

    /**
     * @param array<mixed> $rule
     */
    private static function isRuleValid(array $rule): bool
    {
        $type = $rule['rule'] ?? null;
        if (!\is_string($type) || !isset(self::RULES[$type])) {
            return false;
        }
        $known = ['rule', 'when'];
        foreach (self::RULES[$type] as $key => $kind) {
            $optional = str_starts_with($key, '?');
            $key = ltrim($key, '?');
            $known[] = $key;
            if (!\array_key_exists($key, $rule)) {
                if ($optional) {
                    continue;
                }

                return false;
            }
            if (!self::isOfKind($rule[$key], $kind)) {
                return false;
            }
        }
        if ([] !== array_diff(array_keys($rule), $known)) {
            return false;
        }

        return !\array_key_exists('when', $rule) || (\is_array($rule['when']) && self::isConditionValid($rule['when']));
    }

    private static function isOfKind(mixed $value, string $kind): bool
    {
        return match ($kind) {
            'string' => \is_string($value) && '' !== $value,
            'any-string' => \is_string($value),
            'value' => null === $value || \is_scalar($value),
            'list' => \is_array($value) && [] !== $value && array_is_list($value)
                && [] === array_filter($value, static fn (mixed $item): bool => !\is_string($item) || '' === $item),
            'map' => \is_array($value) && [] !== $value
                && [] === array_filter($value, static fn (mixed $item): bool => null !== $item && !\is_scalar($item)),
            default => false,
        };
    }

    /**
     * @param array<mixed> $condition
     */
    private static function isConditionValid(array $condition): bool
    {
        if (!self::isOfKind($condition['field'] ?? null, 'string')) {
            return false;
        }
        $tests = array_values(array_diff(array_keys($condition), ['field']));
        if (1 !== \count($tests)) {
            return false;
        }

        return match ($tests[0]) {
            'equals' => self::isOfKind($condition['equals'], 'value'),
            'in' => \is_array($condition['in']) && array_is_list($condition['in'])
                && [] === array_filter($condition['in'], static fn (mixed $item): bool => null !== $item && !\is_scalar($item)),
            'empty' => \is_bool($condition['empty']),
            default => false,
        };
    }

    /**
     * @param Condition $condition
     * @param array<mixed> $record
     */
    private static function matches(array $condition, array $record): bool
    {
        $value = $record[$condition['field']] ?? null;
        if (isset($condition['empty'])) {
            return self::isEmpty($value) === $condition['empty'];
        }
        if (\array_key_exists('equals', $condition)) {
            return self::same($value, $condition['equals']);
        }

        return [] !== array_filter($condition['in'] ?? [], static fn (mixed $item): bool => self::same($value, $item));
    }

    /**
     * Values from a database come as strings, values in a pipeline may be
     * numbers: 5 and '5' are the same, null only equals null.
     */
    private static function same(mixed $value, mixed $expected): bool
    {
        if (null === $value || null === $expected) {
            return $value === $expected;
        }

        return \is_scalar($value) && \is_scalar($expected) && self::key($value) === self::key($expected);
    }

    private static function key(bool|float|int|string $value): string
    {
        return \is_bool($value) ? ($value ? '1' : '0') : (string)$value;
    }

    private static function isEmpty(mixed $value): bool
    {
        return null === $value || '' === $value;
    }

    /**
     * @param Rule $rule
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    private static function apply(array $rule, array $record): array
    {
        $field = $rule['field'] ?? '';
        $from = $rule['from'] ?? [];
        $sources = \is_array($from) ? $from : [$from];
        switch ($rule['rule']) {
            case 'fallback':
                if (self::isEmpty($record[$field] ?? null)) {
                    foreach ($sources as $source) {
                        if (!self::isEmpty($record[$source] ?? null)) {
                            $record[$field] = $record[$source];
                            break;
                        }
                    }
                }
                break;
            case 'default':
                if (self::isEmpty($record[$field] ?? null)) {
                    $record[$field] = $rule['value'] ?? null;
                }
                break;
            case 'copy':
            case 'rename':
                $source = $sources[0] ?? '';
                $value = $record[$source] ?? null;
                if ('rename' === $rule['rule']) {
                    unset($record[$source]);
                }
                $record[$field] = $value;
                break;
            case 'map':
                $value = $record[$field] ?? null;
                $values = $rule['values'] ?? [];
                if (\is_scalar($value) && \array_key_exists(self::key($value), $values)) {
                    $record[$field] = $values[self::key($value)];
                } elseif (\array_key_exists('otherwise', $rule)) {
                    $record[$field] = $rule['otherwise'];
                }
                break;
            case 'remove':
                foreach ($rule['fields'] ?? [] as $name) {
                    $record = \array_key_exists($name, $record) || !str_contains($name, '.')
                        ? array_diff_key($record, [$name => null])
                        : self::removePath($record, explode('.', $name));
                }
                break;
            case 'trim':
                if (\is_string($record[$field] ?? null)) {
                    $record[$field] = trim($record[$field]);
                }
                break;
            case 'wrap':
                $value = $record[$field] ?? null;
                if (\is_scalar($value) && '' !== $value) {
                    $record[$field] = ($rule['prefix'] ?? '') . self::key($value) . ($rule['suffix'] ?? '');
                }
                break;
            case 'join':
                $parts = [];
                foreach ($sources as $source) {
                    $value = $record[$source] ?? null;
                    if (\is_scalar($value) && '' !== $value) {
                        $parts[] = self::key($value);
                    }
                }
                $record[$field] = implode($rule['separator'] ?? ' ', $parts);
                break;
        }

        return $record;
    }

    /**
     * @param array<mixed> $data
     * @param non-empty-list<string> $segments
     *
     * @return array<mixed>
     */
    private static function removePath(array $data, array $segments): array
    {
        $segment = array_shift($segments);
        $keys = '*' === $segment ? array_keys($data) : (\array_key_exists($segment, $data) ? [$segment] : []);
        foreach ($keys as $key) {
            if ([] === $segments) {
                unset($data[$key]);
            } elseif (\is_array($data[$key])) {
                $data[$key] = self::removePath($data[$key], $segments);
            }
        }

        return $data;
    }
}
