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
    private const TRANSFER_TYPES = ['import', 'export'];
    private const SECTIONS = ['tasks', 'sets'];

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
     * Returns the import and export configuration in the form
     * `['import' => ['tasks' => [...], 'sets' => [...]], 'export' => [...]]`.
     * Other keys of the file are ignored.
     *
     * @param string $path Path to YAML file
     * @throws FileNotFoundException If file does not exist
     * @throws ParseException If YAML parsing fails
     * @return array<string, array<string, array>>
     */
    public function load(string $path): array
    {
        $configuration = $this->parser->parseFile($path);
        return $this->normalize(\is_array($configuration) ? $configuration : []);
    }

    /**
     * Keeps the tasks and sets of the import and export sections
     *
     * @param array $configuration YAML-parsed configuration
     * @return array<string, array<string, array>>
     */
    protected function normalize(array $configuration): array
    {
        $result = [];
        foreach (self::TRANSFER_TYPES as $type) {
            foreach (self::SECTIONS as $section) {
                if (isset($configuration[$type][$section]) && \is_array($configuration[$type][$section])) {
                    $result[$type][$section] = $configuration[$type][$section];
                }
            }
        }

        return $result;
    }
}
