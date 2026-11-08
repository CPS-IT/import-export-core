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
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;

/**
 * Loader for YAML configuration files.
 */
class YamlConfigurationLoader implements ConfigurationLoaderInterface
{
    /**
     * YamlConfigurationLoader constructor.
     */
    public function __construct(
        private readonly YamlConfigurationParser $parser,
    ) {
    }

    /**
     * Load configuration from YAML file
     *
     * @param string $path Path to YAML file
     * @throws FileNotFoundException If file does not exist
     * @throws ParseException If YAML parsing fails
     * @return array Configuration array in TypoScript-compatible format
     */
    public function load(string $path): array
    {
        $configuration = $this->parser->parseFile($path);
        return $this->convertToTypoScriptFormat($configuration);
    }

    /**
     * Converts YAML structure to TypoScript-compatible array format
     *
     * @param array $configuration YAML-parsed configuration
     * @return array TypoScript-compatible configuration array
     */
    protected function convertToTypoScriptFormat(array $configuration): array
    {
        $result = [];

        // Handle import tasks
        if (isset($configuration['import']['tasks'])) {
            $result['module']['tx_t3importexport']['settings']['import']['tasks'] =
                $configuration['import']['tasks'];
        }

        // Handle import sets
        if (isset($configuration['import']['sets'])) {
            $result['module']['tx_t3importexport']['settings']['import']['sets'] =
                $configuration['import']['sets'];
        }

        // Handle export tasks
        if (isset($configuration['export']['tasks'])) {
            $result['module']['tx_t3importexport']['settings']['export']['tasks'] =
                $configuration['export']['tasks'];
        }

        // Handle export sets
        if (isset($configuration['export']['sets'])) {
            $result['module']['tx_t3importexport']['settings']['export']['sets'] =
                $configuration['export']['sets'];
        }

        return $result;
    }
}
