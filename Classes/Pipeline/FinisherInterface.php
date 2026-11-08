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

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Runs once after all records of a task were persisted.
 */
interface FinisherInterface extends ComponentInterface
{
    public function finish(ResultInterface $result): void;
}
