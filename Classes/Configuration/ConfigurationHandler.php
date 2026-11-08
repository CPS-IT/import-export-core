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

namespace CPSIT\ImportExportCore\Configuration;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;

/**
 * Manages configuration from multiple sources.
 */
class ConfigurationHandler implements TransferConfigurationInterface, ConfigurationHandlerInterface
{
    /**
     * @var array
     */
    private array $configuration = [];

    /**
     * Add configuration from a loader
     *
     * @param ConfigurationLoaderInterface $loader
     * @param string $path Path to configuration file
     * @throws FileNotFoundException
     * @throws ParseException
     * @return self
     */
    public function addConfiguration(ConfigurationLoaderInterface $loader, string $path): self
    {
        $newConfig = $loader->load($path);
        $this->configuration = array_merge_recursive($this->configuration, $newConfig);
        return $this;
    }

    /**
     * Get import/export tasks
     *
     * @return array
     */
    public function getTasks(): array
    {
        $tasks = [];

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['import']['tasks'])) {
            $tasks['import'] = $this->configuration['module']['tx_t3importexport']['settings']['import']['tasks'];
        }

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['export']['tasks'])) {
            $tasks['export'] = $this->configuration['module']['tx_t3importexport']['settings']['export']['tasks'];
        }

        return $tasks;
    }

    /**
     * Get import/export sets
     *
     * @return array
     */
    public function getSets(): array
    {
        $sets = [];

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['import']['sets'])) {
            $sets['import'] = $this->configuration['module']['tx_t3importexport']['settings']['import']['sets'];
        }

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['export']['sets'])) {
            $sets['export'] = $this->configuration['module']['tx_t3importexport']['settings']['export']['sets'];
        }

        return $sets;
    }

    /**
     * Get the full configuration array
     *
     * @return array
     */
    public function getFullConfiguration(): array
    {
        return $this->configuration;
    }
}
