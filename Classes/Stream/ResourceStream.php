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
 * PSR-7 stream around a PHP stream resource, e.g. an opened file.
 *
 * Whether the stream is readable, writable and seekable follows from the mode
 * and the metadata of the resource. The resource is closed with the stream.
 */
class ResourceStream implements StreamInterface
{
    /**
     * @var resource|null
     */
    private $resource;

    private bool $readable;

    private bool $writable;

    private bool $seekable;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
    {
        if (!\is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new \InvalidArgumentException('A stream resource is required', 1795200001);
        }
        $this->resource = $resource;
        $metadata = stream_get_meta_data($resource);
        $mode = $metadata['mode'];
        $this->readable = str_contains($mode, 'r') || str_contains($mode, '+');
        $this->writable = strpbrk($mode, 'waxc+') !== false;
        $this->seekable = $metadata['seekable'];
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
            if ($this->seekable) {
                $this->rewind();
            }

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
        return $this->resource !== null && $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $resource = $this->attachedResource();
        if (!$this->seekable || fseek($resource, $offset, $whence) === -1) {
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
        return $this->resource !== null && $this->writable;
    }

    public function write(string $string): int
    {
        $resource = $this->attachedResource();
        if (!$this->writable) {
            throw new \RuntimeException('Stream is not writable', 1795200002);
        }
        $written = fwrite($resource, $string);
        if ($written === false) {
            throw new \RuntimeException('Unable to write to stream', 1762675203);
        }

        return $written;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null && $this->readable;
    }

    public function read(int $length): string
    {
        if ($length < 1) {
            throw new \RuntimeException('Length must be a positive integer', 1762675204);
        }
        $resource = $this->attachedResource();
        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable', 1795200003);
        }

        $data = fread($resource, $length);
        if ($data === false) {
            throw new \RuntimeException('Unable to read from stream', 1762675205);
        }

        return $data;
    }

    public function getContents(): string
    {
        $resource = $this->attachedResource();
        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable', 1795200003);
        }
        $contents = stream_get_contents($resource);
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
     * Returns a PHP stream resource with the content of the given stream, for
     * functions such as `fgetcsv()` or `XMLReader`. A `ResourceStream` is
     * detached and its resource returned; the content of other streams, and
     * of resources that are not seekable if `$seekable` is set, is copied
     * into a temporary stream. The given stream cannot be used afterwards.
     *
     * @return resource
     */
    public static function toResource(StreamInterface $stream, bool $seekable = false)
    {
        if ($stream instanceof self && $stream->resource !== null) {
            if (!$seekable || $stream->seekable) {
                return $stream->detach();
            }
            $resource = $stream->detach();
            $copy = self::openTemporary();
            stream_copy_to_stream($resource, $copy);
            fclose($resource);
            rewind($copy);

            return $copy;
        }

        $copy = self::openTemporary();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }
            fwrite($copy, $chunk);
        }
        $stream->close();
        rewind($copy);

        return $copy;
    }

    /**
     * @return resource
     */
    private static function openTemporary()
    {
        $resource = fopen('php://temp/maxmemory:' . TemporaryStream::DEFAULT_MEMORY_LIMIT, 'r+b');
        if ($resource === false) {
            throw new \RuntimeException('Unable to open temporary stream', 1762675200);
        }

        return $resource;
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
