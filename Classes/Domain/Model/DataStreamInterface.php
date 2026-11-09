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

namespace CPSIT\ImportExportCore\Domain\Model;

use Psr\Http\Message\StreamInterface;

/**
 * Holds the serialized output of an export (e.g. XML).
 *
 * The output is kept in a PSR-7 stream, so large exports can be written and
 * read in chunks instead of being held in memory as one string.
 */
interface DataStreamInterface
{
    /**
     * Returns the stream. An empty stream if none was set.
     */
    public function getStream(): StreamInterface;

    public function setStream(StreamInterface $stream): void;

    /**
     * Replaces the stream with one containing the given string. Null sets an
     * empty stream.
     *
     * @deprecated since 0.2.0, use setStream() instead
     */
    public function setStreamBuffer(?string $buffer): void;

    /**
     * Returns the whole content of the stream, null if it is empty.
     *
     * @deprecated since 0.2.0, use getStream() instead
     */
    public function getStreamBuffer(): ?string;
}
