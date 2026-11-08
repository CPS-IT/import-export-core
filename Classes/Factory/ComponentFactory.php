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
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyComponents;

/**
 * Components implementing the deprecated counterpart of a pipeline interface
 * are wrapped in an adapter.
 */
final class ComponentFactory implements ComponentFactoryInterface
{
    private readonly InstantiatorInterface $instantiator;

    public function __construct(
        private readonly ComponentRegistryInterface $registry,
        ?InstantiatorInterface $instantiator = null,
    ) {
        $this->instantiator = $instantiator ?? new Instantiator();
    }

    public function create(string $interface, array $settings, ?string $context = null): ComponentInterface
    {
        $name = $settings['class'] ?? null;
        if ($name !== null && !\is_string($name)) {
            throw new InvalidConfigurationException(
                \sprintf('Option "class" must be a string%s.', $this->describe($context)),
                1794230011
            );
        }
        $configuration = $settings['config'] ?? [];
        if (!\is_array($configuration)) {
            throw new InvalidConfigurationException(
                \sprintf('Option "config" must be an array%s.', $this->describe($context)),
                1794230012
            );
        }

        try {
            $className = $this->registry->resolve($interface, $name);
        } catch (InvalidConfigurationException $exception) {
            throw new InvalidConfigurationException(
                $exception->getMessage() . ($context !== null ? ' Task: ' . $context . '.' : ''),
                $exception->getCode(),
                $exception
            );
        }

        $component = $this->instantiator->instantiate($className);

        if ($component instanceof IdentifiableInterface && isset($settings['identifier'])) {
            if (!\is_string($settings['identifier'])) {
                throw new InvalidConfigurationException(
                    \sprintf('Option "identifier" must be a string%s.', $this->describe($context)),
                    1794230013
                );
            }
            $component->setIdentifier($settings['identifier']);
        }

        if (method_exists($component, 'setConfiguration')) {
            /** @var array<string, mixed> $configuration */
            $component->setConfiguration($configuration);
        }

        $component = LegacyComponents::adapt($component, $interface);
        if (!$component instanceof $interface) {
            throw new InvalidConfigurationException(
                \sprintf('Class "%s" must implement "%s"%s.', $className, $interface, $this->describe($context)),
                1794230014
            );
        }

        return $component;
    }

    private function describe(?string $context): string
    {
        return $context !== null ? ' in task "' . $context . '"' : '';
    }
}
