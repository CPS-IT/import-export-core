<?php
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

namespace CPSIT\ImportExportCore\Configuration;


use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;

/**
 * Manages configuration from multiple sources.
 */
interface ConfigurationManagerInterface
{
    /**
     * Add configuration from a loader
     *
     * @param ConfigurationLoaderInterface $loader
     * @param string $path Path to configuration file
     * @return ConfigurationManagerInterface
     * @throws FileNotFoundException
     * @throws ParseException
     */
    public function addConfiguration(ConfigurationLoaderInterface $loader, string $path): ConfigurationManagerInterface;

    /**
     * Get import/export tasks
     *
     * @return array
     */
    public function getTasks(): array;

    /**
     * Get import/export sets
     *
     * @return array
     */
    public function getSets(): array;

    /**
     * Get the full configuration array
     *
     * @return array
     */
    public function getFullConfiguration(): array;
}
