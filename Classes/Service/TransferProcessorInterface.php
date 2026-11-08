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

namespace CPSIT\ImportExportCore\Service;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTaskInterface;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Processes a single transfer task.
 */
interface TransferProcessorInterface
{
    /**
     * Processes the task and returns the result.
     *
     * If a result is given, records and messages are added to it. This allows
     * several tasks to share one result.
     *
     * @throws InvalidConfigurationException if the task has no data source or no data target
     */
    public function process(TransferTaskInterface $task, ?ResultInterface $result = null): ResultInterface;
}
