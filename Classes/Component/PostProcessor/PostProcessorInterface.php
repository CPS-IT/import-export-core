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

namespace CPSIT\ImportExportCore\Component\PostProcessor;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Interface PostProcessorInterface
 *
 * @deprecated since 0.2.0, implement `CPSIT\ImportExportCore\Pipeline\PostProcessorInterface` instead.
 *             Components implementing this interface are wrapped in an adapter.
 */
interface PostProcessorInterface extends ComponentInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @param mixed $convertedRecord
     * @param array<string, mixed> $record
     * @return bool
     */
    public function process(array $configuration, mixed &$convertedRecord, array &$record): bool;

    /**
     * @param array<string, mixed> $configuration
     */
    public function isConfigurationValid(array $configuration): bool;

    /**
     * Tells if the component is disabled
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $record
     */
    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool;

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
