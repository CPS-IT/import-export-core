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

use CPSIT\ImportExportCore\Domain\Model\TransferSet;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Builds a transfer set and its tasks.
 *
 * Task configurations are looked up by identifier in the task definitions
 * passed to `create()`, e.g. the `tasks` of the `import` or `export` section
 * of `ConfigurationHandlerInterface::getFullConfiguration()`.
 */
class TransferSetFactory
{
    public function __construct(private readonly TransferTaskFactory $transferTaskFactory)
    {
    }

    /**
     * Settings:
     *
     * * `tasks`: comma-separated string or list of task identifiers
     * * `label`, `description`
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $taskDefinitions task identifier => task settings
     * @throws InvalidConfigurationException if a task is not defined
     */
    public function create(string $identifier, array $settings, array $taskDefinitions): TransferSet
    {
        $set = new TransferSet();
        $set->setIdentifier($identifier);

        if (isset($settings['label']) && \is_string($settings['label'])) {
            $set->setLabel($settings['label']);
        }
        if (isset($settings['description']) && \is_string($settings['description'])) {
            $set->setDescription($settings['description']);
        }

        $tasks = [];
        foreach ($this->getTaskIdentifiers($identifier, $settings['tasks'] ?? []) as $taskIdentifier) {
            $taskSettings = $taskDefinitions[$taskIdentifier] ?? null;
            if (!\is_array($taskSettings)) {
                throw new InvalidConfigurationException(
                    \sprintf('Set "%s" refers to task "%s", which is not defined.', $identifier, $taskIdentifier),
                    1794230031
                );
            }
            /** @var array<string, mixed> $taskSettings */
            $tasks[$taskIdentifier] = $this->transferTaskFactory->create($taskIdentifier, $taskSettings);
        }
        $set->setTasks($tasks);

        return $set;
    }

    /**
     * @throws InvalidConfigurationException
     * @return list<string>
     */
    private function getTaskIdentifiers(string $identifier, mixed $tasks): array
    {
        if (\is_string($tasks)) {
            $tasks = explode(',', $tasks);
        }
        if (!\is_array($tasks)) {
            throw new InvalidConfigurationException(
                \sprintf('Invalid configuration for set "%s": tasks must be a string or a list.', $identifier),
                1794230032
            );
        }

        $identifiers = [];
        foreach ($tasks as $task) {
            if (!\is_string($task)) {
                throw new InvalidConfigurationException(
                    \sprintf('Invalid configuration for set "%s": task identifiers must be strings.', $identifier),
                    1794230033
                );
            }
            $task = trim($task);
            if ($task !== '') {
                $identifiers[] = $task;
            }
        }

        return $identifiers;
    }
}
