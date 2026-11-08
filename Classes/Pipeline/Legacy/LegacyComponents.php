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

namespace CPSIT\ImportExportCore\Pipeline\Legacy;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Component\Converter\ConverterInterface as LegacyConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface as LegacyFinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface as LegacyInitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface as LegacyPostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface as LegacyPreProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\InitializerInterface;
use CPSIT\ImportExportCore\Pipeline\PostProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Maps the pipeline interfaces to the deprecated component interfaces and
 * wraps deprecated components in adapters.
 *
 * @internal
 */
final class LegacyComponents
{
    /**
     * pipeline interface => [deprecated interface, adapter class]
     */
    private const array MAP = [
        InitializerInterface::class => [LegacyInitializerInterface::class, LegacyInitializerAdapter::class],
        PreProcessorInterface::class => [LegacyPreProcessorInterface::class, LegacyPreProcessorAdapter::class],
        ConverterInterface::class => [LegacyConverterInterface::class, LegacyConverterAdapter::class],
        PostProcessorInterface::class => [LegacyPostProcessorInterface::class, LegacyPostProcessorAdapter::class],
        FinisherInterface::class => [LegacyFinisherInterface::class, LegacyFinisherAdapter::class],
    ];

    /**
     * Tells if a class implements the interface or can be adapted to it.
     */
    public static function isCompatible(string $className, string $interface): bool
    {
        if (is_a($className, $interface, true)) {
            return true;
        }

        return isset(self::MAP[$interface]) && is_a($className, self::MAP[$interface][0], true);
    }

    /**
     * Wraps a component implementing the deprecated counterpart of
     * `$interface` in an adapter. Other components are returned unchanged.
     *
     * @param class-string<ComponentInterface> $interface
     */
    public static function adapt(ComponentInterface $component, string $interface): ComponentInterface
    {
        if ($component instanceof $interface || !isset(self::MAP[$interface])) {
            return $component;
        }

        [$legacyInterface, $adapterClass] = self::MAP[$interface];
        if (!$component instanceof $legacyInterface) {
            return $component;
        }

        return new $adapterClass($component);
    }
}
