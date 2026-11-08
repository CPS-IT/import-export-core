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

namespace CPSIT\ImportExportCore\Service;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use Symfony\Component\Yaml\Exception\ParseException as SymfonyParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for parsing YAML configuration files.
 */
class YamlConfigurationParser
{
    /**
     * Parse a YAML file and return the configuration array
     *
     * @param string $filePath Path to YAML file
     * @throws FileNotFoundException If the file does not exist
     * @throws ParseException If YAML parsing fails
     * @return mixed|array Parsed configuration
     */
    public function parseFile(string $filePath): mixed
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('Configuration file not found: ' . $filePath, 1624543211);
        }

        try {
            $result = Yaml::parseFile($filePath);
            return $result ?? [];
        } catch (SymfonyParseException $e) {
            throw new ParseException('Error parsing YAML file: ' . $e->getMessage(), 1624543212, $e);
        }
    }

    /**
     * Parse YAML string and return the configuration array
     *
     * @param string $yamlContent YAML content
     * @throws ParseException If YAML parsing fails
     * @return mixed|array Parsed configuration
     */
    public function parseString(string $yamlContent): mixed
    {
        try {
            $result = Yaml::parse($yamlContent);
            return $result ?? [];
        } catch (SymfonyParseException $e) {
            throw new ParseException('Error parsing YAML content: ' . $e->getMessage(), 1624543213, $e);
        }
    }
}
