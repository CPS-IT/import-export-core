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

namespace CPSIT\ImportExportCore\Component\Converter;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Interface ConverterInterface
 */
interface ConverterInterface extends ComponentInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $record
     */
    public function convert(array &$record, array $configuration): mixed;

    /**
     * Tells if the component is disabled
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $record
     */
    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool;

    /**
     * @param array<string, mixed> $configuration
     * @return bool
     */
    public function isConfigurationValid(array $configuration): bool;

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
