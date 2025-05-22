<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2025 Dirk Wenzel <wenzel@cps-it.de>
 *  All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt, and important notices to the license
 * from the author are found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace CPSIT\ImportExportCore\Domain\Model;

/**
 * Class TransferSet
 * A set of transfer tasks
 */
interface TransferSetInterface
{
    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     */
    public function setDescription(string $description): void;

    /**
     * @return TransferTaskInterface[]
     */
    public function getTasks(): array;

    /**
     * @param TransferTaskInterface[] $tasks
     */
    public function setTasks(array $tasks): void;

    /**
     * Gets the label
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Sets the label
     *
     * @param string $label
     */
    public function setLabel(string $label): void;
}
