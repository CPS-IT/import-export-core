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

namespace CPSIT\ImportExportCore\Domain\Model;

/**
 * Counters of a transfer. Kept by the result independent of whether the
 * converted records are collected.
 */
final class TransferStatistics
{
    private int $read = 0;
    private int $persisted = 0;
    private int $skipped = 0;
    private int $batches = 0;

    public function countRead(int $count = 1): void
    {
        $this->read += $count;
    }

    public function countPersisted(int $count = 1): void
    {
        $this->persisted += $count;
    }

    public function countSkipped(int $count = 1): void
    {
        $this->skipped += $count;
    }

    public function countBatch(): void
    {
        ++$this->batches;
    }

    /**
     * Records read from data sources
     */
    public function getRead(): int
    {
        return $this->read;
    }

    /**
     * Records passed to data targets
     */
    public function getPersisted(): int
    {
        return $this->persisted;
    }

    /**
     * Records not persisted because the data target was disabled for them
     */
    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getBatches(): int
    {
        return $this->batches;
    }
}
