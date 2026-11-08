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

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\IdentifiableTrait;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\InitializerInterface;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyComponents;
use CPSIT\ImportExportCore\Pipeline\PostProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

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
     * @var array<int|string, PreProcessorInterface>
     */
    protected array $preProcessors = [];

    /**
     * @var array<int|string, PostProcessorInterface>
     */
    protected array $postProcessors = [];

    /**
     * @var array<int|string, ConverterInterface>
     */
    protected array $converters = [];

    /**
     * @var array<int|string, FinisherInterface>
     */
    protected array $finishers = [];

    /**
     * @var array<int|string, InitializerInterface>
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
        $this->preProcessors = $this->adapt($preProcessors, PreProcessorInterface::class);
    }

    public function getPostProcessors(): array
    {
        return $this->postProcessors;
    }

    public function setPostProcessors(array $postProcessors): void
    {
        $this->postProcessors = $this->adapt($postProcessors, PostProcessorInterface::class);
    }

    public function getConverters(): array
    {
        return $this->converters;
    }

    public function setConverters(array $converters): void
    {
        $this->converters = $this->adapt($converters, ConverterInterface::class);
    }

    public function getFinishers(): array
    {
        return $this->finishers;
    }

    public function setFinishers(array $finishers): void
    {
        $this->finishers = $this->adapt($finishers, FinisherInterface::class);
    }

    public function getInitializers(): array
    {
        return $this->initializers;
    }

    public function setInitializers(array $initializers): void
    {
        $this->initializers = $this->adapt($initializers, InitializerInterface::class);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @template T of ComponentInterface
     * @param array<int|string, ComponentInterface> $components
     * @param class-string<T> $interface
     * @return array<int|string, T>
     */
    private function adapt(array $components, string $interface): array
    {
        $adapted = [];
        foreach ($components as $key => $component) {
            $component = LegacyComponents::adapt($component, $interface);
            if (!$component instanceof $interface) {
                throw new \InvalidArgumentException(
                    \sprintf('Component "%s" must implement "%s".', $component::class, $interface),
                    1794260003
                );
            }
            $adapted[$key] = $component;
        }

        return $adapted;
    }
}
