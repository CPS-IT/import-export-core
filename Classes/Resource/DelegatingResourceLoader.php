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

namespace CPSIT\ImportExportCore\Resource;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use Psr\Http\Message\StreamInterface;

/**
 * Passes a location to the first of its loaders that supports it.
 */
final class DelegatingResourceLoader implements ResourceLoaderInterface
{
    public const int ERROR_NOT_SUPPORTED = 1795200401;

    /**
     * @var list<ResourceLoaderInterface>
     */
    private readonly array $loaders;

    public function __construct(ResourceLoaderInterface ...$loaders)
    {
        $this->loaders = array_values($loaders);
    }

    public function supports(string $location): bool
    {
        return $this->findLoader($location) !== null;
    }

    public function open(string $location): StreamInterface
    {
        $loader = $this->findLoader($location);
        if ($loader === null) {
            throw new MissingResourceException(
                \sprintf('No loader supports the location "%s".', $location),
                self::ERROR_NOT_SUPPORTED
            );
        }

        return $loader->open($location);
    }

    private function findLoader(string $location): ?ResourceLoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($location)) {
                return $loader;
            }
        }

        return null;
    }
}
