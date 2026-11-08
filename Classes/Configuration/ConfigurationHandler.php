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
     * The new configuration is merged into the existing one: associative
     * arrays are merged recursively, scalar values and lists of the new
     * configuration replace existing ones.
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
        $this->configuration = self::merge($this->configuration, $newConfig);
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
     * Merges $override into $base. Associative arrays are merged recursively;
     * scalar values and lists in $override replace those in $base.
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                \is_array($value)
                && isset($base[$key])
                && \is_array($base[$key])
                && !array_is_list($value)
                && !array_is_list($base[$key])
            ) {
                $base[$key] = self::merge($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
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
