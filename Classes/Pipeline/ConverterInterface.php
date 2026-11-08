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
 * Converts a record into the form the data target expects, e.g. an object.
 * Converters of a task are applied one after the other; each receives the
 * output of the previous one.
 */
interface ConverterInterface extends ComponentInterface
{
    /**
     * @param array<mixed> $record
     */
    public function convert(array $record): mixed;
}
