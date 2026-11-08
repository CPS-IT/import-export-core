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

use Psr\Container\ContainerInterface;

/**
 * Gets objects from a PSR-11 container.
 *
 * The object returned by the container is cloned, because containers usually
 * share their services. Classes the container does not know are passed to the
 * fallback instantiator.
 */
final class ContainerInstantiator implements InstantiatorInterface
{
    private readonly InstantiatorInterface $fallback;

    public function __construct(
        private readonly ContainerInterface $container,
        ?InstantiatorInterface $fallback = null,
    ) {
        $this->fallback = $fallback ?? new Instantiator();
    }

    public function instantiate(string $className): object
    {
        if (!$this->container->has($className)) {
            return $this->fallback->instantiate($className);
        }

        $instance = $this->container->get($className);
        if (!$instance instanceof $className) {
            throw new \UnexpectedValueException(
                \sprintf('Container entry "%s" is not an instance of that class.', $className),
                1794230001
            );
        }

        return clone $instance;
    }
}
