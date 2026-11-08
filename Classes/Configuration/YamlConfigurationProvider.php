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

/**
 * Provider for YAML configuration
 */
class YamlConfigurationProvider
{
    /**
     * YamlConfigurationProvider constructor.
     **/
    public function __construct(
        private readonly ConfigurationHandlerInterface $configurationHandler,
        private readonly YamlConfigurationLoader $yamlLoader
    ) {
    }

    /**
     * Load YAML configuration files from a directory
     *
     * @param string $directory Directory containing YAML files
     * @param string $extension File extension (default: yaml)
     * @return array Loaded configuration
     */
    public function loadFromDirectory(string $directory, string $extension = 'yaml'): array
    {
        if (!is_dir($directory)) {
            return $this->configurationHandler->getFullConfiguration();
        }

        $files = scandir($directory);
        if ($files === false) {
            return $this->configurationHandler->getFullConfiguration();
        }

        $yamlFiles = [];
        foreach ($files as $file) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory . '/' . $file;
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === $extension) {
                $yamlFiles[] = $filePath;
            }
        }

        // Sort files to ensure consistent order
        sort($yamlFiles);

        foreach ($yamlFiles as $file) {
            $this->configurationHandler->addConfiguration($this->yamlLoader, $file);
        }

        return $this->configurationHandler->getFullConfiguration();
    }
}
