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

/**
 * Decides whether a record is processed. Filters run before the
 * pre-processors and receive the record as read from the source. A record
 * that a filter does not accept is skipped and counted as skipped.
 */
interface FilterInterface extends ComponentInterface
{
    /**
     * @param array<mixed> $record
     */
    public function accepts(array $record): bool;
}
