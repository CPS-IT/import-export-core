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


use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;

/**
 * Class TransferTask
 * A transfer task describes a transfer from one source to a target
 */
interface TransferTaskInterface
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
     * @return string
     */
    public function getTargetClass(): string;

    /**
     * @param string $targetClass
     */
    public function setTargetClass(string $targetClass): void;

    /**
     * Gets the source of import
     */
    public function getSource(): ?DataSourceInterface;

    /**
     * Sets the source of import
     */
    public function setSource(DataSourceInterface $source): void;

    /**
     * Gets the target of import
     */
    public function getTarget(): ?DataTargetInterface;

    /**
     * Sets the target of import
     */
    public function setTarget(DataTargetInterface $target);

    /**
     * Gets the pre-processors
     *
     * @return PreProcessorInterface[]
     */
    public function getPreProcessors(): array;

    /**
     * Sets the pre-processors
     * @param PreProcessorInterface[] $preProcessors
     */
    public function setPreProcessors(array $preProcessors): void;

    /**
     * Gets the post-processors
     *
     * @return PostProcessorInterface[]
     */
    public function getPostProcessors(): array;

    /**
     * Sets the post-processors
     *
     * @param PostProcessorInterface[] $postProcessors
     */
    public function setPostProcessors(array $postProcessors);

    /**
     * Gets the converters
     *
     * @return ConverterInterface[]
     */
    public function getConverters(): array;

    /**
     * Sets the converters
     *
     * @param ConverterInterface[] $converters
     */
    public function setConverters(array $converters);

    /**
     * Gets the finishers
     *
     * @return FinisherInterface[]
     */
    public function getFinishers(): array;

    /**
     * sets the finishers
     *
     * @param FinisherInterface[] $finishers
     */
    public function setFinishers(array $finishers);

    /**
     * Gets the initializers
     *
     * @return InitializerInterface[]
     */
    public function getInitializers(): array;

    /**
     * Sets the initializers
     *
     * @param InitializerInterface[] $initializers
     */
    public function setInitializers(array $initializers): void;

    /**
     * Gets the label
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
