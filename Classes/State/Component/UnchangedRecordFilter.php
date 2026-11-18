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
use CPSIT\ImportExportCore\Pipeline\ProcessedRecordFilterInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;
use CPSIT\ImportExportCore\State\IdMapping;

/**
 * Incremental mode: skips records that were written before and did not change
 * since. A record is unchanged if the ID map holds a mapping for its source ID
 * with the checksum of the record (IdMapping::recordChecksum()).
 *
 * Records without a source ID are accepted.
 *
 * The filter runs after the pre-processors: the checksum covers the
 * pre-processed record, so changes in values that pre-processors add, e.g.
 * relation lists or resolved references, are detected. The source ID is taken
 * from the record as read from the source. Converters and post-processors are
 * not covered: after changing them, run the task without this filter once.
 *
 * Configuration:
 *
 * - `namespace` (string, required): namespace of the records in the ID map,
 *   as in the `idMap` settings of the task
 * - `sourceIdField` (string, required): field holding the source ID, as in
 *   the `idMap` settings of the task
 */
final class UnchangedRecordFilter implements ProcessedRecordFilterInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function __construct(private readonly IdMapInterface $idMap)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return isset($configuration['namespace'], $configuration['sourceIdField'])
            && \is_string($configuration['namespace'])
            && \is_string($configuration['sourceIdField'])
            && $configuration['namespace'] !== ''
            && $configuration['sourceIdField'] !== '';
    }

    /**
     * For a record that no pre-processor changed.
     */
    public function accepts(array $record): bool
    {
        return $this->acceptsProcessed($record, $record);
    }

    public function acceptsProcessed(array $record, array $sourceRecord): bool
    {
        $sourceIdField = (string)$this->configuration['sourceIdField'];
        $sourceId = $sourceRecord[$sourceIdField] ?? null;
        if (!\is_scalar($sourceId) || (string)$sourceId === '') {
            return true;
        }

        $mapping = $this->idMap->get((string)$this->configuration['namespace'], (string)$sourceId);

        return !$mapping->exists() || $mapping->checksum !== IdMapping::recordChecksum($record, $sourceIdField);
    }
}
