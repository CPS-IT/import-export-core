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

namespace CPSIT\ImportExportCore\Persistence;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Data targets implementing this interface are notified after each batch,
 * e.g. to write buffered records and free memory. `persistAll()` is still
 * called once, after the last batch.
 */
interface BatchAwareInterface
{
    public function flushBatch(ResultInterface $result): void;
}
