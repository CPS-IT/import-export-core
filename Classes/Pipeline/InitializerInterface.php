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
 * Runs once before the records of a task are processed, e.g. to prepare
 * the target. Initializers do not receive records.
 *
 * Components receive their configuration once, when they are created
 * (`ConfigurableInterface::setConfiguration()`).
 */
interface InitializerInterface extends ComponentInterface
{
    public function initialize(): void;
}
