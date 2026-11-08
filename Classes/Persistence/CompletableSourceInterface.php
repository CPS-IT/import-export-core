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
 * Data sources implementing this interface are told when the records they
 * returned were processed: after `persistAll()` of the target, before the
 * finishers. If processing fails, `complete()` is not called.
 */
interface CompletableSourceInterface
{
    public function complete(ResultInterface $result): void;
}
