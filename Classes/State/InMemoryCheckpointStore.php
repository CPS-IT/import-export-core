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
 * Keeps checkpoints in memory, e.g. for tests.
 */
final class InMemoryCheckpointStore implements CheckpointStoreInterface
{
    /**
     * @var array<string, Checkpoint>
     */
    private array $checkpoints = [];

    public function get(string $taskKey): Checkpoint
    {
        return $this->checkpoints[$taskKey] ?? new Checkpoint($taskKey);
    }

    public function save(Checkpoint $checkpoint): void
    {
        $this->checkpoints[$checkpoint->taskKey] = $checkpoint;
    }

    public function clear(string $taskKey): void
    {
        unset($this->checkpoints[$taskKey]);
    }
}
