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

use CPSIT\ImportExportCore\Stream\TemporaryStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class TemporaryStreamTest extends TestCase
{
    public function testImplementsStreamInterface(): void
    {
        self::assertInstanceOf(StreamInterface::class, new TemporaryStream());
    }

    public function testIsInitiallyEmpty(): void
    {
        $subject = new TemporaryStream();

        self::assertSame(0, $subject->getSize());
        self::assertSame(0, $subject->tell());
        self::assertSame('', (string)$subject);
    }

    public function testInitialContentIsRewound(): void
    {
        $subject = new TemporaryStream('abc');

        self::assertSame(3, $subject->getSize());
        self::assertSame(0, $subject->tell());
        self::assertSame('abc', $subject->getContents());
    }

    public function testIsReadableWritableAndSeekable(): void
    {
        $subject = new TemporaryStream();

        self::assertTrue($subject->isReadable());
        self::assertTrue($subject->isWritable());
        self::assertTrue($subject->isSeekable());
    }

    public function testWritesAndReadsInChunks(): void
    {
        $subject = new TemporaryStream();

        self::assertSame(3, $subject->write('abc'));
        self::assertSame(3, $subject->write('def'));
        self::assertSame(6, $subject->tell());

        $subject->rewind();

        self::assertSame('abcd', $subject->read(4));
        self::assertSame('ef', $subject->read(4));
        self::assertSame('', $subject->read(4));
        self::assertTrue($subject->eof());
    }

    public function testSeeksRelativeToCurrentPositionAndEnd(): void
    {
        $subject = new TemporaryStream('abcdef');

        $subject->seek(2);
        $subject->seek(1, SEEK_CUR);
        self::assertSame('def', $subject->getContents());

        $subject->seek(-2, SEEK_END);
        self::assertSame('ef', $subject->getContents());
    }

    public function testSeekBeforeStartThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1762675202);

        (new TemporaryStream('abc'))->seek(-1);
    }

    public function testToStringReturnsWholeContentRegardlessOfPosition(): void
    {
        $subject = new TemporaryStream('abc');
        $subject->read(2);

        self::assertSame('abc', (string)$subject);
    }

    public function testKeepsContentBeyondMemoryLimit(): void
    {
        $content = str_repeat('x', 4096);
        $subject = new TemporaryStream($content, 1024);

        self::assertSame(4096, $subject->getSize());
        self::assertSame($content, (string)$subject);
    }

    public function testReadWithNonPositiveLengthThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1762675204);

        (new TemporaryStream('abc'))->read(0);
    }

    public function testReturnsMetadata(): void
    {
        $subject = new TemporaryStream();

        self::assertSame('PHP', $subject->getMetadata('wrapper_type'));
        self::assertIsArray($subject->getMetadata());
        self::assertNull($subject->getMetadata('unknown'));
    }

    public function testDetachReturnsResourceAndLeavesStreamUnusable(): void
    {
        $subject = new TemporaryStream('abc');

        $resource = $subject->detach();

        self::assertIsResource($resource);
        self::assertSame('abc', stream_get_contents($resource, -1, 0));
        self::assertNull($subject->detach());
        self::assertNull($subject->getSize());
        self::assertFalse($subject->isReadable());
        self::assertFalse($subject->isWritable());
        self::assertFalse($subject->isSeekable());
        self::assertTrue($subject->eof());
        self::assertSame('', (string)$subject);
        self::assertSame([], $subject->getMetadata());
        self::assertNull($subject->getMetadata('mode'));
        fclose($resource);
    }

    public function testCloseLeavesStreamUnusable(): void
    {
        $subject = new TemporaryStream('abc');

        $subject->close();

        self::assertNull($subject->detach());
        self::assertSame('', (string)$subject);
    }

    /**
     * @return iterable<string, array{\Closure(StreamInterface): mixed}>
     */
    public static function operationsOnDetachedStreamProvider(): iterable
    {
        yield 'tell' => [static fn (StreamInterface $stream): mixed => $stream->tell()];
        yield 'seek' => [static fn (StreamInterface $stream): mixed => $stream->seek(0)];
        yield 'rewind' => [static fn (StreamInterface $stream): mixed => $stream->rewind()];
        yield 'write' => [static fn (StreamInterface $stream): mixed => $stream->write('x')];
        yield 'read' => [static fn (StreamInterface $stream): mixed => $stream->read(1)];
        yield 'getContents' => [static fn (StreamInterface $stream): mixed => $stream->getContents()];
    }

    /**
     * @param \Closure(StreamInterface): mixed $operation
     */
    #[DataProvider('operationsOnDetachedStreamProvider')]
    public function testOperationOnDetachedStreamThrows(\Closure $operation): void
    {
        $subject = new TemporaryStream('abc');
        $subject->detach();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1762675207);

        $operation($subject);
    }
}
