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

namespace CPSIT\ImportExportCore\Persistence;

/**
 * Data targets implementing this interface report the IDs of the records they
 * wrote. If the task has ID map settings, the processor records the mapping
 * from source ID to target ID for each written record.
 *
 * The processor asks after the record was written: for batch aware targets
 * after `flushBatch()`, for other targets right after `persist()`. With a
 * batch aware target, a record cannot reference another record of the same
 * batch through the ID map; use a batch size of 1 or a second task for such
 * references (e.g. the parent page in a page tree).
 */
interface IdentifyingTargetInterface
{
    /**
     * Returns the ID of a record this target persisted.
     *
     * @param array<mixed>|object $record the record as passed to `persist()`
     */
    public function getTargetId(array|object $record): string;
}
