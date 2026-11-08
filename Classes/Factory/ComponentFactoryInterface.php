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
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Builds a component from its configuration.
 */
interface ComponentFactoryInterface
{
    /**
     * Settings:
     *
     * * `class`: registered short name or fully qualified class name;
     *   optional if a default is registered for the interface
     * * `identifier`: set on components implementing `IdentifiableInterface`
     * * `config`: passed to `setConfiguration()`; defaults to `[]`
     *
     * @template T of ComponentInterface
     * @param class-string<T> $interface Role of the component, e.g. `PreProcessorInterface::class`
     * @param array<string, mixed> $settings
     * @param string|null $context Identifier of the task, used in error messages
     * @throws InvalidConfigurationException
     * @return T
     */
    public function create(string $interface, array $settings, ?string $context = null): ComponentInterface;
}
