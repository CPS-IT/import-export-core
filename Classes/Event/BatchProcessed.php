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
 * Dispatched after a batch was persisted (and flushed, if the target is
 * batch aware). Listeners can report progress or pause to limit the load on
 * source and target.
 */
final readonly class BatchProcessed
{
    /**
     * @param int $batchNumber starts with 1 for each task
     * @param int $recordCount records of this batch
     */
    public function __construct(
        public TransferTaskInterface $task,
        public string $taskKey,
        public int $batchNumber,
        public int $recordCount,
        public ResultInterface $result,
    ) {
    }
}
