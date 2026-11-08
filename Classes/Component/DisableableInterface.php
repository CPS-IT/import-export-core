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

namespace CPSIT\ImportExportCore\Component;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Components that can be disabled by configuration, e.g. per record.
 *
 * The component interfaces for initializers, processors, converters and
 * finishers declare the same method. Data targets implement this interface if
 * they support being disabled.
 */
interface DisableableInterface
{
    /**
     * Tells if the component is disabled
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $record
     */
    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool;
}
