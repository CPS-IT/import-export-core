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

namespace CPSIT\ImportExportCore\File;

/**
 * The options `file` and `url` of file sources: the location passed to the
 * resource loader. Exactly one of them must be set.
 *
 * @internal
 */
final class ResourceLocation
{
    public const string KEY_FILE = 'file';
    public const string KEY_URL = 'url';

    /**
     * @param array<string, mixed> $configuration
     */
    public static function isValid(array $configuration): bool
    {
        $file = $configuration[self::KEY_FILE] ?? null;
        $url = $configuration[self::KEY_URL] ?? null;
        if (($file === null) === ($url === null)) {
            return false;
        }
        $location = $file ?? $url;

        return \is_string($location) && trim($location) !== '';
    }

    /**
     * Whether the location is a file (`file`), not a URL
     *
     * @param array<string, mixed> $configuration
     */
    public static function isFile(array $configuration): bool
    {
        return isset($configuration[self::KEY_FILE]);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromConfiguration(array $configuration): string
    {
        $location = $configuration[self::KEY_FILE] ?? $configuration[self::KEY_URL] ?? '';

        return \is_string($location) ? $location : '';
    }
}
