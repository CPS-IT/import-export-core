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

namespace CPSIT\ImportExportCore\Pipeline;

/**
 * A filter that runs after the pre-processors. It receives the pre-processed
 * record and the record as read from the source, e.g. to compare values that
 * pre-processors add (relations, resolved references) while identifying the
 * record by its source ID.
 *
 * The processor calls acceptsProcessed() instead of accepts() for these
 * filters. A record that a filter does not accept is skipped and counted as
 * skipped; its pre-processors have run, its converters have not.
 */
interface ProcessedRecordFilterInterface extends FilterInterface
{
    /**
     * @param array<mixed> $record the record after the pre-processors
     * @param array<mixed> $sourceRecord the record as read from the source
     */
    public function acceptsProcessed(array $record, array $sourceRecord): bool;
}
