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
 * Stores the position of each task, so an interrupted run can resume.
 */
interface CheckpointStoreInterface
{
    /**
     * Returns the checkpoint of the task, an initial one if none was saved.
     */
    public function get(string $taskKey): Checkpoint;

    /**
     * Saves the checkpoint, replacing the previous one of the task.
     */
    public function save(Checkpoint $checkpoint): void;

    /**
     * Removes the checkpoint of the task, e.g. after it completed.
     */
    public function clear(string $taskKey): void;
}
