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

namespace CPSIT\ImportExportCore\State;

/**
 * Position of a task after its last completed batch.
 */
final readonly class Checkpoint
{
    /**
     * @param string $taskKey identifier of the task
     * @param int $batchNumber number of the last completed batch
     * @param int $recordsProcessed records processed up to and including that batch
     * @param string $cursor position reported by a resumable source, '' if none
     */
    public function __construct(
        public string $taskKey,
        public int $batchNumber = 0,
        public int $recordsProcessed = 0,
        public string $cursor = '',
    ) {
        if ($batchNumber < 0 || $recordsProcessed < 0) {
            throw new \InvalidArgumentException('Batch number and processed records must not be negative.', 1794500001);
        }
    }

    /**
     * Whether the task has not processed any batch yet.
     */
    public function isInitial(): bool
    {
        return $this->batchNumber === 0;
    }
}
