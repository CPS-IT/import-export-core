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

namespace CPSIT\ImportExportCore\Domain\Model;

use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\IdentifiableTrait;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;

/**
 * Describes a transfer from one data source to one data target.
 */
class TransferTask implements IdentifiableInterface, TransferTaskInterface
{
    use IdentifiableTrait;

    protected string $targetClass = '';
    protected string $label = '';
    protected string $description = '';
    protected ?DataSourceInterface $source = null;
    protected ?DataTargetInterface $target = null;

    /**
     * @var PreProcessorInterface[]
     */
    protected array $preProcessors = [];

    /**
     * @var PostProcessorInterface[]
     */
    protected array $postProcessors = [];

    /**
     * @var ConverterInterface[]
     */
    protected array $converters = [];

    /**
     * @var FinisherInterface[]
     */
    protected array $finishers = [];

    /**
     * @var InitializerInterface[]
     */
    protected array $initializers = [];

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getTargetClass(): string
    {
        return $this->targetClass;
    }

    public function setTargetClass(string $targetClass): void
    {
        $this->targetClass = $targetClass;
    }

    public function getSource(): ?DataSourceInterface
    {
        return $this->source;
    }

    public function setSource(DataSourceInterface $source): void
    {
        $this->source = $source;
    }

    public function getTarget(): ?DataTargetInterface
    {
        return $this->target;
    }

    public function setTarget(DataTargetInterface $target): void
    {
        $this->target = $target;
    }

    public function getPreProcessors(): array
    {
        return $this->preProcessors;
    }

    public function setPreProcessors(array $preProcessors): void
    {
        $this->preProcessors = $preProcessors;
    }

    public function getPostProcessors(): array
    {
        return $this->postProcessors;
    }

    public function setPostProcessors(array $postProcessors): void
    {
        $this->postProcessors = $postProcessors;
    }

    public function getConverters(): array
    {
        return $this->converters;
    }

    public function setConverters(array $converters): void
    {
        $this->converters = $converters;
    }

    public function getFinishers(): array
    {
        return $this->finishers;
    }

    public function setFinishers(array $finishers): void
    {
        $this->finishers = $finishers;
    }

    public function getInitializers(): array
    {
        return $this->initializers;
    }

    public function setInitializers(array $initializers): void
    {
        $this->initializers = $initializers;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
