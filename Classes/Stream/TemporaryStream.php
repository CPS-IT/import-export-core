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

namespace CPSIT\ImportExportCore\Stream;

/**
 * PSR-7 stream backed by php://temp.
 *
 * Data is kept in memory up to the given limit and moved to a temporary file
 * beyond it. The stream is always readable, writable and seekable until it is
 * closed or detached.
 */
final class TemporaryStream extends ResourceStream
{
    /**
     * Default memory limit of php://temp (2 MB).
     */
    public const int DEFAULT_MEMORY_LIMIT = 2097152;

    public function __construct(string $content = '', int $memoryLimit = self::DEFAULT_MEMORY_LIMIT)
    {
        $resource = fopen('php://temp/maxmemory:' . $memoryLimit, 'r+b');
        if ($resource === false) {
            throw new \RuntimeException('Unable to open temporary stream', 1762675200);
        }
        parent::__construct($resource);

        if ($content !== '') {
            $this->write($content);
            $this->rewind();
        }
    }
}
