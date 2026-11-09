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

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTaskInterface;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\CheckpointStoreInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;

/**
 * The run of one task, as seen by initializers and finishers.
 */
final readonly class TaskContext
{
    /**
     * @param string $taskKey identifier of the task in this run
     * @param Checkpoint $resumedFrom checkpoint the run started from; initial unless an interrupted run is resumed
     */
    public function __construct(
        public string $taskKey,
        public TransferTaskInterface $task,
        public ResultInterface $result,
        public IdMapInterface $idMap,
        public CheckpointStoreInterface $checkpointStore,
        public Checkpoint $resumedFrom,
    ) {
    }

    /**
     * Whether this run continues an interrupted one. Initializers that reset
     * the target should not do so in that case.
     */
    public function isResumed(): bool
    {
        return !$this->resumedFrom->isInitial();
    }
}
