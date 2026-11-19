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

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Allowed file types of a file source or target, by file name extension.
 *
 * Executable files are never allowed, whatever the list of allowed
 * extensions contains: names ending in `.php`, `.php3` to `.php8`, `.phpsh`,
 * `.phtml`, `.pht`, `.phar`, `.shtml`, `.cgi` or `.pl`, also followed by
 * further extensions (`news.php.csv`), and `.htaccess`. The pattern is the
 * same as the default `fileDenyPattern` of TYPO3.
 *
 * The extensions are set by the application, e.g. as constructor argument of
 * a component, not by the task configuration.
 */
final readonly class FileTypePolicy
{
    public const int ERROR_NOT_ALLOWED = 1795200801;
    public const string DENY_PATTERN = '\.(php[3-8]?|phpsh|phtml|pht|phar|shtml|cgi)(\..*)?$|\.pl$|^\.htaccess$';

    /**
     * @var list<string>
     */
    private array $allowedExtensions;

    /**
     * @param list<string> $allowedExtensions extensions without dot, e.g.
     *                                        `['csv']`; an empty list allows
     *                                        every file that is not executable
     */
    public function __construct(array $allowedExtensions = [])
    {
        $this->allowedExtensions = array_values(array_unique(array_map(
            static fn (string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $allowedExtensions
        )));
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function isAllowed(string $path): bool
    {
        $fileName = self::fileName($path);
        if ($fileName === '' || self::isDenied($fileName)) {
            return false;
        }
        if ($this->allowedExtensions === []) {
            return true;
        }

        return \in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), $this->allowedExtensions, true);
    }

    /**
     * @throws InvalidConfigurationException if the file type is not allowed
     */
    public function assertAllowed(string $path): void
    {
        if ($this->isAllowed($path)) {
            return;
        }

        throw new InvalidConfigurationException(
            $this->allowedExtensions === []
                ? \sprintf('The file "%s" has a type that is not allowed.', $path)
                : \sprintf(
                    'The file "%s" has a type that is not allowed. Allowed: %s.',
                    $path,
                    implode(', ', $this->allowedExtensions)
                ),
            self::ERROR_NOT_ALLOWED
        );
    }

    /**
     * Whether a file name is executable (`DENY_PATTERN`) or contains control
     * characters
     */
    public static function isDenied(string $path): bool
    {
        $fileName = self::fileName($path);

        // preg_match() returns false for invalid UTF-8, which is denied as well
        return preg_match('/[[:cntrl:]]/u', $path) !== 0
            || preg_match('/' . self::DENY_PATTERN . '/iu', $fileName) !== 0;
    }

    private static function fileName(string $path): string
    {
        $segments = preg_split('#[/\\\\]+#', $path) ?: [];

        return (string)end($segments);
    }
}
