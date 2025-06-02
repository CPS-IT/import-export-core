<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Service;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use Symfony\Component\Yaml\Exception\ParseException as SymfonyParseException;
use Symfony\Component\Yaml\Yaml;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2025 Dirk Wenzel <wenzel@cps-it.de>
 *  All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
