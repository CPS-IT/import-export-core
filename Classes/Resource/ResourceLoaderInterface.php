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
 * Opens resources, e.g. files or URLs, named by a location from a
 * configuration.
 */
interface ResourceLoaderInterface
{
    /**
     * Tells whether this loader handles the location, e.g. by its scheme.
     */
    public function supports(string $location): bool;

    /**
     * Opens the resource for reading. The caller closes the stream.
     *
     * @throws MissingResourceException if the resource is not supported, not
     *                                  allowed or cannot be opened
     */
    public function open(string $location): StreamInterface;
}
