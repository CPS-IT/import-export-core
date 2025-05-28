<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Configuration;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;

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
     * @return self
     * @throws FileNotFoundException
     * @throws ParseException
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
