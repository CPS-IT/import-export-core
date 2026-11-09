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
 * Data sources implementing this interface can continue reading after a given
 * position instead of starting from the beginning.
 *
 * After each batch, the processor stores the cursor of the source in a
 * checkpoint. When an interrupted task runs again, the processor calls
 * `resumeFrom()` with that cursor before `getRecords()`. Sources that do not
 * implement this interface are read from the beginning and the records that
 * were already processed are skipped.
 */
interface ResumableSourceInterface
{
    /**
     * Returns the position after the last record yielded by `getRecords()`,
     * e.g. the ID of that record. '' if no record was yielded.
     */
    public function getCursor(): string;

    /**
     * The next call of `getRecords()` starts after the given position.
     */
    public function resumeFrom(string $cursor): void;
}
