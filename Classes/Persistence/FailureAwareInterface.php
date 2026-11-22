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

/**
 * For data sources and targets that have to act when a task fails, e.g. to
 * roll back a transaction or to mark claimed items as failed.
 *
 * The processor calls `fail()` on the target and then on the source of a task
 * when an exception ends the task, after the task started and before the
 * exception is passed on. `complete()` and `persistAll()` are not called then.
 * Exceptions thrown by `fail()` are added to the result as error messages
 * (`DataTransferProcessor::ERROR_FAIL_THREW`) only; the original exception is
 * passed on.
 */
interface FailureAwareInterface
{
    public function fail(\Throwable $error): void;
}
