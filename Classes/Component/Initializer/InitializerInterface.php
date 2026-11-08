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

namespace CPSIT\ImportExportCore\Component\Initializer;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;

/**
 * Interface InitializerInterface
 */
interface InitializerInterface extends ComponentInterface
{
    /**
     * @param array $records Array with prepared records
     */
    public function process(array $configuration, array &$records): bool;

    public function isConfigurationValid(array $configuration): bool;

    /**
     * Tells if the component is disabled
     */
    public function isDisabled(array $configuration, array $record = [], ?TaskResult $result = null): bool;

    /**
     * Sets the configuration
     */
    public function setConfiguration(array $configuration): void;

    /**
     * Returns the configuration
     */
    public function getConfiguration(): array;
}
