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

use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 stream backed by php://temp.
 *
 * Data is kept in memory up to the given limit and moved to a temporary file
 * beyond it. The stream is always readable, writable and seekable until it is
 * closed or detached.
 */
final class TemporaryStream implements StreamInterface
{
    /**
     * Default memory limit of php://temp (2 MB).
     */
    public const int DEFAULT_MEMORY_LIMIT = 2097152;

    /**
     * @var resource|null
     */
    private $resource;

    public function __construct(string $content = '', int $memoryLimit = self::DEFAULT_MEMORY_LIMIT)
    {
        $resource = fopen('php://temp/maxmemory:' . $memoryLimit, 'r+b');
        if ($resource === false) {
            throw new \RuntimeException('Unable to open temporary stream', 1762675200);
        }
        $this->resource = $resource;

        if ($content !== '') {
            $this->write($content);
            $this->rewind();
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            $this->rewind();

            return $this->getContents();
        } catch (\RuntimeException) {
            return '';
        }
    }

    public function close(): void
    {
        $resource = $this->detach();
        if (\is_resource($resource)) {
            fclose($resource);
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats === false ? null : $stats['size'];
    }

    public function tell(): int
    {
        $position = ftell($this->attachedResource());
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position', 1762675201);
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (fseek($this->attachedResource(), $offset, $whence) === -1) {
            throw new \RuntimeException(
                \sprintf('Unable to seek to stream position %d with whence %d', $offset, $whence),
                1762675202
            );
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->resource !== null;
    }

    public function write(string $string): int
    {
        $written = fwrite($this->attachedResource(), $string);
        if ($written === false) {
            throw new \RuntimeException('Unable to write to stream', 1762675203);
        }

        return $written;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null;
    }

    public function read(int $length): string
    {
        if ($length < 1) {
            throw new \RuntimeException('Length must be a positive integer', 1762675204);
        }

        $data = fread($this->attachedResource(), $length);
        if ($data === false) {
            throw new \RuntimeException('Unable to read from stream', 1762675205);
        }

        return $data;
    }

    public function getContents(): string
    {
        $contents = stream_get_contents($this->attachedResource());
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents', 1762675206);
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }

    /**
     * @return resource
     */
    private function attachedResource()
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached', 1762675207);
        }

        return $this->resource;
    }
}
