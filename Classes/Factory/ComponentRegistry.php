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

use CPSIT\ImportExportCore\Exception\InvalidClassException;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyComponents;

/**
 * Classes implementing the deprecated counterpart of a pipeline interface
 * (`CPSIT\\ImportExportCore\\Component\\`) are accepted for that interface.
 */
final class ComponentRegistry implements ComponentRegistryInterface
{
    /**
     * @var array<string, array<string, class-string>>
     */
    private array $classes = [];

    /**
     * @var array<string, class-string>
     */
    private array $defaults = [];

    public function register(string $interface, string $name, string $className): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('The name of a component must not be empty.', 1794230002);
        }
        $this->assertImplements($interface, $className);
        $this->classes[$interface][$name] = $className;
    }

    public function setDefault(string $interface, string $className): void
    {
        $this->assertImplements($interface, $className);
        $this->defaults[$interface] = $className;
    }

    public function resolve(string $interface, ?string $name = null): string
    {
        if ($name === null || $name === '') {
            $className = $this->defaults[$interface] ?? null;
            if ($className === null) {
                throw new InvalidConfigurationException(
                    \sprintf('Missing class for component of type "%s" and no default is registered.', $interface),
                    1794230003
                );
            }
        } else {
            $className = $this->classes[$interface][$name] ?? $name;
        }

        if (!class_exists($className)) {
            throw new InvalidConfigurationException(
                \sprintf('Class "%s" for component of type "%s" does not exist.', $className, $interface),
                1794230004
            );
        }
        if (!LegacyComponents::isCompatible($className, $interface)) {
            throw new InvalidConfigurationException(
                \sprintf('Class "%s" must implement "%s".', $className, $interface),
                1794230005
            );
        }

        return $className;
    }

    public function getRegistered(string $interface): array
    {
        return $this->classes[$interface] ?? [];
    }

    private function assertImplements(string $interface, string $className): void
    {
        if (!LegacyComponents::isCompatible($className, $interface)) {
            throw new InvalidClassException(
                \sprintf('Class "%s" must implement "%s".', $className, $interface),
                1794230006
            );
        }
    }
}
