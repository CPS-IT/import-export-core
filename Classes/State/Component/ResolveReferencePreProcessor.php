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

namespace CPSIT\ImportExportCore\State\Component;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;

/**
 * Replaces source IDs in a field with the target IDs from the ID map, e.g. the
 * parent page of a page or the category of an event.
 *
 * Configuration:
 *
 * - `field`: field holding the reference (required)
 * - `namespace`: namespace of the referenced records in the ID map (required)
 * - `separator`: for fields holding several IDs as a string, e.g. `,`
 * - `onMissing`: what happens if a referenced ID has no mapping:
 *   `fail` (default) throws an exception, `keep` keeps the source ID, `empty`
 *   removes it. `keep` and `empty` log a warning.
 *
 * The field may also hold a list of IDs. Empty values (`null`, `''`, `0`) are
 * left unchanged.
 */
final class ResolveReferencePreProcessor implements PreProcessorInterface, ConfigurableInterface, LoggingInterface
{
    use ConfigurableTrait;
    use LoggingTrait;

    public const string ON_MISSING_FAIL = 'fail';
    public const string ON_MISSING_KEEP = 'keep';
    public const string ON_MISSING_EMPTY = 'empty';

    public function __construct(private readonly IdMapInterface $idMap)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        foreach (['field', 'namespace'] as $key) {
            if (!isset($configuration[$key]) || !\is_string($configuration[$key]) || $configuration[$key] === '') {
                return false;
            }
        }
        if (isset($configuration['separator']) && (!\is_string($configuration['separator']) || $configuration['separator'] === '')) {
            return false;
        }

        return \in_array(
            $configuration['onMissing'] ?? self::ON_MISSING_FAIL,
            [self::ON_MISSING_FAIL, self::ON_MISSING_KEEP, self::ON_MISSING_EMPTY],
            true
        );
    }

    public function process(array $record): array
    {
        $field = (string)$this->configuration['field'];
        $value = $record[$field] ?? null;
        if (self::isEmpty($value)) {
            return $record;
        }

        $separator = $this->configuration['separator'] ?? null;
        if (\is_array($value)) {
            $record[$field] = $this->resolveList($value);
        } elseif (\is_string($separator) && \is_string($value)) {
            $record[$field] = implode($separator, $this->resolveList(explode($separator, $value)));
        } else {
            $record[$field] = $this->resolveList([$value])[0] ?? '';
        }

        return $record;
    }

    /**
     * @param array<mixed> $ids
     * @return list<mixed>
     */
    private function resolveList(array $ids): array
    {
        $resolved = [];
        foreach ($ids as $id) {
            if (self::isEmpty($id)) {
                continue;
            }
            if (!\is_scalar($id)) {
                throw new \UnexpectedValueException(
                    \sprintf('Field "%s" must hold IDs, got %s.', $this->configuration['field'], get_debug_type($id)),
                    1794600011
                );
            }
            $mapping = $this->idMap->get((string)$this->configuration['namespace'], trim((string)$id));
            if ($mapping->exists()) {
                $resolved[] = $mapping->targetId;
                continue;
            }
            $this->handleMissing((string)$id);
            if (($this->configuration['onMissing'] ?? self::ON_MISSING_FAIL) === self::ON_MISSING_KEEP) {
                $resolved[] = $id;
            }
        }

        return $resolved;
    }

    private function handleMissing(string $id): void
    {
        $description = \sprintf(
            'No mapping for ID "%s" of field "%s" in namespace "%s".',
            $id,
            $this->configuration['field'],
            $this->configuration['namespace']
        );
        $onMissing = $this->configuration['onMissing'] ?? self::ON_MISSING_FAIL;
        if ($onMissing === self::ON_MISSING_FAIL) {
            throw new \UnexpectedValueException($description, 1794600012);
        }

        $this->logMessage('Unresolved reference', $description . ' ' . ($onMissing === self::ON_MISSING_KEEP ? 'The source ID is kept.' : 'The ID is removed.'), Message::SEVERITY_WARNING, 1794600013);
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0' || $value === [];
    }
}
