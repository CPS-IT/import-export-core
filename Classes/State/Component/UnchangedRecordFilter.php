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
use CPSIT\ImportExportCore\Pipeline\FilterInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;
use CPSIT\ImportExportCore\State\IdMapping;

/**
 * Incremental mode: skips records that were written before and did not change
 * since. A record is unchanged if the ID map holds a mapping for its source ID
 * with the checksum of the record.
 *
 * Configuration: `namespace` and `sourceIdField`, as in the `idMap` settings of
 * the task. Records without a source ID are accepted.
 *
 * The checksum covers the record as read from the source. After a change of
 * the pipeline configuration, run the task without this filter once.
 */
final class UnchangedRecordFilter implements FilterInterface, ConfigurableInterface
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

    public function accepts(array $record): bool
    {
        $sourceId = $record[(string)$this->configuration['sourceIdField']] ?? null;
        if (!\is_scalar($sourceId) || (string)$sourceId === '') {
            return true;
        }

        $mapping = $this->idMap->get((string)$this->configuration['namespace'], (string)$sourceId);

        return !$mapping->exists() || $mapping->checksum !== IdMapping::checksum($record);
    }
}
