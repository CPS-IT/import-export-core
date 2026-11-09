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

use CPSIT\ImportExportCore\Stream\TemporaryStream;
use Psr\Http\Message\StreamInterface;

class DataStream implements DataStreamInterface
{
    protected StreamInterface $stream;

    public function __construct(?StreamInterface $stream = null)
    {
        $this->stream = $stream ?? new TemporaryStream();
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    public function setStream(StreamInterface $stream): void
    {
        $this->stream = $stream;
    }

    /**
     * @deprecated since 0.2.0, use setStream() instead
     */
    public function setStreamBuffer(?string $buffer): void
    {
        $this->stream = new TemporaryStream($buffer ?? '');
    }

    /**
     * @deprecated since 0.2.0, use getStream() instead
     */
    public function getStreamBuffer(): ?string
    {
        $buffer = (string)$this->stream;

        return $buffer === '' ? null : $buffer;
    }
}
