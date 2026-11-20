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
 * For data targets that have to act when a task reads no records, e.g. to
 * replace the output of the previous run with an empty one.
 *
 * The processor calls `persistAll()` only if records were read. For a target
 * with this interface it calls `persistEmptyRun()` instead if the source
 * returned no records. It is not called if the run resumed from a checkpoint,
 * since the records of the interrupted run were written.
 */
interface EmptyRunAwareInterface
{
    /**
     * @param array<string, mixed>|null $configuration configuration of the
     *                                                 target, as for `persistAll()`
     */
    public function persistEmptyRun(ResultInterface $result, ?array $configuration = null): void;
}
