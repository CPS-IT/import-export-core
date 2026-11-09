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
 * Stores nothing, so every run starts from the beginning. Default of the
 * processor if no checkpoint store is given.
 */
final class NullCheckpointStore implements CheckpointStoreInterface
{
    public function get(string $taskKey): Checkpoint
    {
        return new Checkpoint($taskKey);
    }

    public function save(Checkpoint $checkpoint): void
    {
    }

    public function clear(string $taskKey): void
    {
    }
}
