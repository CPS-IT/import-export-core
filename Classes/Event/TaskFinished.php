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

namespace CPSIT\ImportExportCore\Event;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTaskInterface;

/**
 * Dispatched after the finishers of a task ran.
 */
final readonly class TaskFinished
{
    /**
     * @param int $recordCount records read for this task
     */
    public function __construct(
        public TransferTaskInterface $task,
        public string $taskKey,
        public int $recordCount,
        public ResultInterface $result,
    ) {
    }
}
