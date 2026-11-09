<?php declare(strict_types=1);

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

namespace CPSIT\ImportExportCore\Domain\Model;

use CPSIT\ImportExportCore\Component\Converter\ConverterInterface as LegacyConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface as LegacyFinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface as LegacyInitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface as LegacyPostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface as LegacyPreProcessorInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;
use CPSIT\ImportExportCore\Pipeline\FilterInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\InitializerInterface;
use CPSIT\ImportExportCore\Pipeline\PostProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\State\IdMapSettings;

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
    public function setTarget(DataTargetInterface $target): void;

    /**
     * Gets the filters
     *
     * @return array<int|string, FilterInterface>
     */
    public function getFilters(): array;

    /**
     * Sets the filters
     *
     * @param array<int|string, FilterInterface> $filters
     */
    public function setFilters(array $filters): void;

    /**
     * Gets the ID map settings; disabled unless set.
     */
    public function getIdMapSettings(): IdMapSettings;

    public function setIdMapSettings(IdMapSettings $settings): void;

    /**
     * Gets the pre-processors
     *
     * @return array<int|string, PreProcessorInterface>
     */
    public function getPreProcessors(): array;

    /**
     * Sets the pre-processors
     * Components implementing the deprecated interface are wrapped in an adapter.
     *
     * @param array<int|string, PreProcessorInterface|LegacyPreProcessorInterface> $preProcessors
     */
    public function setPreProcessors(array $preProcessors): void;

    /**
     * Gets the post-processors
     *
     * @return array<int|string, PostProcessorInterface>
     */
    public function getPostProcessors(): array;

    /**
     * Sets the post-processors
     *
     * Components implementing the deprecated interface are wrapped in an adapter.
     *
     * @param array<int|string, PostProcessorInterface|LegacyPostProcessorInterface> $postProcessors
     */
    public function setPostProcessors(array $postProcessors): void;

    /**
     * Gets the converters
     *
     * @return array<int|string, ConverterInterface>
     */
    public function getConverters(): array;

    /**
     * Sets the converters
     *
     * Components implementing the deprecated interface are wrapped in an adapter.
     *
     * @param array<int|string, ConverterInterface|LegacyConverterInterface> $converters
     */
    public function setConverters(array $converters): void;

    /**
     * Gets the finishers
     *
     * @return array<int|string, FinisherInterface>
     */
    public function getFinishers(): array;

    /**
     * sets the finishers
     *
     * Components implementing the deprecated interface are wrapped in an adapter.
     *
     * @param array<int|string, FinisherInterface|LegacyFinisherInterface> $finishers
     */
    public function setFinishers(array $finishers): void;

    /**
     * Gets the initializers
     *
     * @return array<int|string, InitializerInterface>
     */
    public function getInitializers(): array;

    /**
     * Sets the initializers
     *
     * Components implementing the deprecated interface are wrapped in an adapter.
     *
     * @param array<int|string, InitializerInterface|LegacyInitializerInterface> $initializers
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
