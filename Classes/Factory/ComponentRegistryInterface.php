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
use CPSIT\ImportExportCore\Exception\InvalidClassException;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Maps short names to component classes.
 *
 * Names are registered per component interface (role), e.g.
 * `PreProcessorInterface::class`. The same name can be used for different
 * roles.
 */
interface ComponentRegistryInterface
{
    /**
     * @param class-string<ComponentInterface> $interface
     * @param class-string $className
     * @throws InvalidClassException if the class does not implement the interface
     */
    public function register(string $interface, string $name, string $className): void;

    /**
     * Sets the class used if a configuration does not name a class.
     *
     * @param class-string<ComponentInterface> $interface
     * @param class-string $className
     * @throws InvalidClassException if the class does not implement the interface
     */
    public function setDefault(string $interface, string $className): void;

    /**
     * Returns the class for a registered short name or a fully qualified
     * class name. Without a name, the default class is returned.
     *
     * @template T of ComponentInterface
     * @param class-string<T> $interface
     * @throws InvalidConfigurationException if no class can be resolved
     * @return class-string<T>
     */
    public function resolve(string $interface, ?string $name = null): string;

    /**
     * @param class-string<ComponentInterface> $interface
     * @return array<string, class-string> short name => class name
     */
    public function getRegistered(string $interface): array;
}
