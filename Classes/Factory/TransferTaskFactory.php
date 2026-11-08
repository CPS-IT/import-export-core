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

namespace CPSIT\ImportExportCore\Factory;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;

/**
 * Builds a transfer task from the configuration of a task.
 */
class TransferTaskFactory
{
    final public const int MISSING_SOURCE_EXCEPTION_CODE = 1451206701;
    final public const int MISSING_TARGET_EXCEPTION_CODE = 1451052262;

    public function __construct(private readonly ComponentFactoryInterface $componentFactory)
    {
    }

    /**
     * @param array<string, mixed> $settings
     * @throws InvalidConfigurationException
     */
    public function create(string $identifier, array $settings): TransferTask
    {
        if (!isset($settings['source']) || !\is_array($settings['source'])) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid configuration for task "%s": source is missing or is not an array.', $identifier),
                self::MISSING_SOURCE_EXCEPTION_CODE
            );
        }
        if (!isset($settings['target']) || !\is_array($settings['target'])) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid configuration for task "%s": target is missing or is not an array.', $identifier),
                self::MISSING_TARGET_EXCEPTION_CODE
            );
        }

        $task = new TransferTask();
        $task->setIdentifier($identifier);
        $task->setSource($this->componentFactory->create(DataSourceInterface::class, $settings['source'], $identifier));
        $task->setTarget($this->componentFactory->create(DataTargetInterface::class, $settings['target'], $identifier));

        if (isset($settings['class']) && \is_string($settings['class'])) {
            $task->setTargetClass($settings['class']);
        }
        if (isset($settings['label']) && \is_string($settings['label'])) {
            $task->setLabel($settings['label']);
        }
        if (isset($settings['description']) && \is_string($settings['description'])) {
            $task->setDescription($settings['description']);
        }

        $task->setInitializers($this->createComponents(InitializerInterface::class, $settings, 'initializers', $identifier));
        $task->setPreProcessors($this->createComponents(PreProcessorInterface::class, $settings, 'preProcessors', $identifier));
        $task->setConverters($this->createComponents(ConverterInterface::class, $settings, 'converters', $identifier));
        $task->setPostProcessors($this->createComponents(PostProcessorInterface::class, $settings, 'postProcessors', $identifier));
        $task->setFinishers($this->createComponents(FinisherInterface::class, $settings, 'finishers', $identifier));

        return $task;
    }

    /**
     * Keys of the configuration are kept; they determine the order.
     *
     * @template T of ComponentInterface
     * @param class-string<T> $interface
     * @param array<string, mixed> $settings
     * @throws InvalidConfigurationException
     * @return array<int|string, T>
     */
    private function createComponents(string $interface, array $settings, string $key, string $identifier): array
    {
        $list = $settings[$key] ?? [];
        if (!\is_array($list)) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid configuration for task "%s": %s must be an array.', $identifier, $key),
                1794230021
            );
        }

        $components = [];
        foreach ($list as $position => $componentSettings) {
            if (!\is_array($componentSettings)) {
                throw new InvalidConfigurationException(
                    \sprintf('Invalid configuration for task "%s": %s.%s must be an array.', $identifier, $key, $position),
                    1794230022
                );
            }
            /** @var array<string, mixed> $componentSettings */
            $components[$position] = $this->componentFactory->create($interface, $componentSettings, $identifier);
        }

        return $components;
    }
}
