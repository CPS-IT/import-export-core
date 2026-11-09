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
 * Data targets implementing this interface report the ID of the record they
 * wrote. If the task has ID map settings, the processor records the mapping
 * from source ID to target ID after each `persist()`.
 *
 * The record must be written, or its ID reserved, when `persist()` returns:
 * the mapping is used right away, e.g. to resolve references of later records.
 */
interface IdentifyingTargetInterface
{
    /**
     * Returns the ID of the record written by the last call of `persist()`.
     */
    public function getLastTargetId(): string;
}
