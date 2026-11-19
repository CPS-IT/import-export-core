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

namespace CPSIT\ImportExportCore\Tests\Unit\Stream;

use CPSIT\ImportExportCore\Stream\ResourceStream;
use CPSIT\ImportExportCore\Stream\TemporaryStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class ResourceStreamTest extends TestCase
{
    public function testRequiresStreamResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1795200001);

        new ResourceStream('not a resource');
    }

    public function testReadOnlyStreamIsNotWritable(): void
    {
        $subject = new ResourceStream($this->open('abc', 'rb'));

        self::assertTrue($subject->isReadable());
        self::assertFalse($subject->isWritable());
        self::assertTrue($subject->isSeekable());
        self::assertSame('abc', $subject->getContents());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1795200002);
        $subject->write('d');
    }

    public function testWriteOnlyStreamIsNotReadable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ie');
        $resource = fopen((string)$file, 'wb');
        self::assertIsResource($resource);
        $subject = new ResourceStream($resource);

        try {
            self::assertFalse($subject->isReadable());
            self::assertTrue($subject->isWritable());
            self::assertSame(3, $subject->write('abc'));

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionCode(1795200003);
            $subject->read(1);
        } finally {
            $subject->close();
            unlink((string)$file);
        }
    }

    public function testToResourceDetachesResourceStream(): void
    {
        $resource = $this->open('abc', 'rb');
        $subject = new ResourceStream($resource);

        self::assertSame($resource, ResourceStream::toResource($subject));
        self::assertNull($subject->detach());
    }

    public function testToResourceCopiesOtherStreams(): void
    {
        $source = new TemporaryStream('abc');
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturnCallback(static fn (): bool => $source->eof());
        $stream->method('read')->willReturnCallback(static fn (int $length): string => $source->read($length));

        $resource = ResourceStream::toResource($stream);

        self::assertSame('abc', stream_get_contents($resource));
    }

    /**
     * @return resource
     */
    private function open(string $content, string $mode)
    {
        $file = tempnam(sys_get_temp_dir(), 'ie');
        file_put_contents((string)$file, $content);
        $resource = fopen((string)$file, $mode);
        unlink((string)$file);
        self::assertIsResource($resource);

        return $resource;
    }
}
