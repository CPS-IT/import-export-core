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

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Trait ConfigurableTrait
 */
trait ConfigurableTrait
{
    /**
     * Configuration for this component
     * A plain configuration array
     */
    protected array $configuration = [];

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Sets the configuration if it is valid.
     * Throws an exception otherwise.
     *
     * @throws InvalidConfigurationException
     */
    public function setConfiguration(array $configuration): void
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new InvalidConfigurationException(
                'Configuration for ' . $this::class
                . ' is not valid.',
                1_451_659_793
            );
        }

        $this->configuration = $configuration;
    }

    /**
     * Tells if a given configuration is valid
     */
    abstract public function isConfigurationValid(array $configuration): bool;
}
