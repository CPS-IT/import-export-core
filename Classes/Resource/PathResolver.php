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

/**
 * Resolves relative paths against a base directory and does not allow paths
 * outside of it.
 *
 * Absolute paths, stream wrappers (`scheme://`), `..` segments, null bytes
 * and executable file names (`FileTypePolicy::isDenied()`, e.g. `.php`) are
 * rejected. If the path or one of its parent directories exists, its real
 * path must be inside the base directory, so symbolic links cannot lead out
 * of it either.
 */
final class PathResolver implements PathResolverInterface
{
    public const int ERROR_NOT_ALLOWED = 1795200101;
    public const int ERROR_INVALID_BASE_DIRECTORY = 1795200102;

    private readonly string $baseDirectory;

    /**
     * @param string|null $baseDirectory existing directory; default: the
     *                                   current working directory
     */
    public function __construct(?string $baseDirectory = null)
    {
        $directory = $baseDirectory ?? getcwd();
        $realDirectory = \is_string($directory) ? realpath($directory) : false;
        if ($realDirectory === false || !is_dir($realDirectory)) {
            throw new InvalidArgumentException(
                \sprintf('Base directory "%s" does not exist.', (string)$directory),
                self::ERROR_INVALID_BASE_DIRECTORY
            );
        }
        $this->baseDirectory = rtrim($realDirectory, '/\\');
    }

    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    public function resolve(string $path): string
    {
        if (!self::isAllowed($path)) {
            throw $this->notAllowed($path);
        }

        $segments = array_filter(
            preg_split('#[/\\\\]+#', $path) ?: [],
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        );
        if ($segments === []) {
            throw $this->notAllowed($path);
        }
        $absolutePath = $this->baseDirectory . '/' . implode('/', $segments);

        $existing = $absolutePath;
        while (!file_exists($existing) && $existing !== $this->baseDirectory) {
            $existing = \dirname($existing);
        }
        $realPath = realpath($existing);
        if ($realPath === false || !$this->isInside($realPath)) {
            throw $this->notAllowed($path);
        }

        return $absolutePath;
    }

    private static function isAllowed(string $path): bool
    {
        if (trim($path) === '' || str_contains($path, "\0")) {
            return false;
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $path) === 1) {
            // stream wrapper or Windows drive
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return false;
        }
        if (FileTypePolicy::isDenied($path)) {
            return false;
        }

        return !\in_array('..', preg_split('#[/\\\\]+#', $path) ?: [], true);
    }

    private function isInside(string $realPath): bool
    {
        return $realPath === $this->baseDirectory
            || str_starts_with($realPath, $this->baseDirectory . \DIRECTORY_SEPARATOR);
    }

    private function notAllowed(string $path): InvalidArgumentException
    {
        return new InvalidArgumentException(
            \sprintf('Path "%s" is not allowed. Use a path relative to the base directory.', $path),
            self::ERROR_NOT_ALLOWED
        );
    }
}
