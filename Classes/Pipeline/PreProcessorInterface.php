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
 * Changes a record before it is converted.
 */
interface PreProcessorInterface extends ComponentInterface
{
    /**
     * @param array<mixed> $record
     * @return array<mixed> the processed record
     */
    public function process(array $record): array;
}
