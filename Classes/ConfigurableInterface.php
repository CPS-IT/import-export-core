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

namespace CPSIT\ImportExportCore;

/**
 * Interface ConfigurableInterface
 */
interface ConfigurableInterface
{
    public const KEY_CONFIG = 'config';
    public const KEY_DISABLED = 'disabled';

    /**
     * Tells if a given configuration is valid
     * @param array<string, mixed> $configuration
     */
    public function isConfigurationValid(array $configuration): bool;

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array;

    /**
     * Sets the configuration if it is valid.
     * Throws an exception otherwise.
     *
     * @param array<string, mixed> $configuration
     * @throws \CPSIT\ImportExportCore\Exception\InvalidConfigurationException
     */
    public function setConfiguration(array $configuration): void;
}
