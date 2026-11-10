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
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;

/**
 * Upsert by ID map: sets the target ID of a record written in an earlier run,
 * so the target updates that record instead of inserting it again.
 *
 * The source ID is looked up in the ID map. If there is a mapping, its target
 * ID is set in `targetIdField`. Otherwise `targetIdField` is removed, so a
 * source ID never reaches the target ID column and the target inserts the
 * record. Use together with the `idMap` settings of the task and a target
 * that updates by `targetIdField`, e.g. `DataTargetDBAL` with that field as
 * `key` and `idField`.
 *
 * Configuration:
 *
 * - `namespace`: namespace of the records in the ID map (required)
 * - `sourceIdField`: field holding the source ID (required)
 * - `targetIdField`: field for the target ID (default: `sourceIdField`)
 *
 * The source ID is read before `targetIdField` is changed, so both may be the
 * same field.
 */
final class TargetIdPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function __construct(private readonly IdMapInterface $idMap)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        foreach (['namespace', 'sourceIdField'] as $key) {
            if (!isset($configuration[$key]) || !\is_string($configuration[$key]) || $configuration[$key] === '') {
                return false;
            }
        }

        return !isset($configuration['targetIdField'])
            || (\is_string($configuration['targetIdField']) && $configuration['targetIdField'] !== '');
    }

    public function process(array $record): array
    {
        $sourceIdField = (string)$this->configuration['sourceIdField'];
        $targetIdField = (string)($this->configuration['targetIdField'] ?? $sourceIdField);
        $sourceId = $record[$sourceIdField] ?? null;

        if (\is_scalar($sourceId) && (string)$sourceId !== '') {
            $mapping = $this->idMap->get((string)$this->configuration['namespace'], (string)$sourceId);
            if ($mapping->exists()) {
                $record[$targetIdField] = $mapping->targetId;

                return $record;
            }
        }
        unset($record[$targetIdField]);

        return $record;
    }
}
