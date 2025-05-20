<?php

namespace CPSIT\ImportExportCore\Component;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
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
 * Interface ConfigurableInterface
 */
interface ConfigurableInterface
{
    public const KEY_TABLE = 'table';
    public const KEY_CONFIG = 'config';
    public const KEY_WHERE = 'where';
    public const KEY_DISABLED = 'disabled';
    public const KEY_SET_FIELDS = 'setFields';
    public const KEY_TYPES = 'types';

    /**
     * Tells if a given configuration is valid
     */
    public function isConfigurationValid(array $configuration): bool;

    /**
     * @return array
     */
    public function getConfiguration(): array;

    /**
     * Sets the configuration if it is valid.
     * Throws an exception otherwise.
     *
     * @throws InvalidConfigurationException
     */
    public function setConfiguration(array $configuration);
}
