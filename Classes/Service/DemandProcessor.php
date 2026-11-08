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

use CPSIT\ImportExportCore\Domain\Model\Dto\DemandInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;

/**
 * Processes all tasks of a demand in order. The tasks share one result.
 */
class DemandProcessor
{
    public function __construct(
        protected readonly TransferProcessorInterface $transferProcessor
    ) {
    }

    public function process(DemandInterface $demand, ?ResultInterface $result = null): ResultInterface
    {
        $result ??= new TaskResult();
        foreach ($demand->getTasks() as $task) {
            $result = $this->transferProcessor->process($task, $result);
        }

        return $result;
    }
}
