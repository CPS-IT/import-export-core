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

use CPSIT\ImportExportCore\Exception\InvalidArgumentException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Stream\ResourceStream;
use Psr\Http\Message\StreamInterface;

/**
 * Opens local files. The location is a path, which the path resolver turns
 * into an absolute path (by default relative to the current working
 * directory, see {@see PathResolver}).
 */
final class FileResourceLoader implements ResourceLoaderInterface
{
    public const int ERROR_NOT_FOUND = 1795200201;

    private readonly PathResolverInterface $pathResolver;

    public function __construct(?PathResolverInterface $pathResolver = null)
    {
        $this->pathResolver = $pathResolver ?? new PathResolver();
    }

    /**
     * Locations without a scheme (`scheme://`).
     */
    public function supports(string $location): bool
    {
        return $location !== '' && preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $location) !== 1;
    }

    public function open(string $location): StreamInterface
    {
        if (!$this->supports($location)) {
            throw $this->missing($location);
        }
        try {
            $path = $this->pathResolver->resolve($location);
        } catch (InvalidArgumentException $exception) {
            throw new MissingResourceException($exception->getMessage(), $exception->getCode(), $exception);
        }
        if (!is_file($path) || !is_readable($path)) {
            throw $this->missing($location);
        }
        $resource = @fopen($path, 'rb');
        if ($resource === false) {
            throw $this->missing($location);
        }

        return new ResourceStream($resource);
    }

    private function missing(string $location): MissingResourceException
    {
        return new MissingResourceException(
            \sprintf('File "%s" does not exist or cannot be read.', $location),
            self::ERROR_NOT_FOUND
        );
    }
}
