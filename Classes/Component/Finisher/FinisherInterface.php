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

namespace CPSIT\ImportExportCore\Component\Finisher;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;

/**
 * Interface FinisherInterface
 */
interface FinisherInterface extends ComponentInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<mixed> $records Array with prepared records
     * @param array<mixed>|object $result Array with result records
     * @return bool
     */
    public function process(array $configuration, array &$records, array|object &$result): bool;

    /**
     * @param array<string, mixed> $configuration
     * @return bool
     */
    public function isConfigurationValid(array $configuration): bool;

    /**
     * Tells if the component is disabled
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $record
     */
    public function isDisabled(array $configuration, array $record = [], ?TaskResult $result = null): bool;

    /**
     * Sets the configuration
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration): void;

    /**
     * Returns the configuration
     * @return array<string, mixed>
     */
    public function getConfiguration(): array;
}
