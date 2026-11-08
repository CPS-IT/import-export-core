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
 * Changes a converted record before it is persisted.
 */
interface PostProcessorInterface extends ComponentInterface
{
    /**
     * @param mixed $convertedRecord output of the converters
     * @param array<mixed> $record the record before conversion
     * @return mixed the processed converted record
     */
    public function process(mixed $convertedRecord, array $record): mixed;
}
