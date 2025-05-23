<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Configuration;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;

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
 * Loader for YAML configuration files.
 */
class YamlConfigurationLoader implements ConfigurationLoaderInterface
{
    /**
     * @var YamlConfigurationParser
     */
    private YamlConfigurationParser $parser;
    
    /**
     * YamlConfigurationLoader constructor.
     */
    public function __construct(YamlConfigurationParser $parser)
    {
        $this->parser = $parser;
    }
    
    /**
     * Load configuration from YAML file
     *
     * @param string $path Path to YAML file
     * @return array Configuration array in TypoScript-compatible format
     * @throws FileNotFoundException If file does not exist
     * @throws ParseException If YAML parsing fails
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
