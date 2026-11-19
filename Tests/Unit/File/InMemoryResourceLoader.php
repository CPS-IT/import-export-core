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

namespace CPSIT\ImportExportCore\Tests\Unit\File;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;
use CPSIT\ImportExportCore\Stream\TemporaryStream;
use Psr\Http\Message\StreamInterface;

/**
 * Resource loader serving fixed contents by location.
 */
final class InMemoryResourceLoader implements ResourceLoaderInterface
{
    /**
     * @param array<string, string> $contents
     * @param bool $seekable whether the returned streams are seekable
     */
    public function __construct(
        private array $contents = [],
        private readonly bool $seekable = true,
    ) {
    }

    public function set(string $location, string $content): void
    {
        $this->contents[$location] = $content;
    }

    public function supports(string $location): bool
    {
        return \array_key_exists($location, $this->contents);
    }

    public function open(string $location): StreamInterface
    {
        if (!$this->supports($location)) {
            throw new MissingResourceException('missing ' . $location, 1);
        }
        $stream = new TemporaryStream($this->contents[$location]);

        return $this->seekable ? $stream : new NonSeekableStream($stream);
    }
}
