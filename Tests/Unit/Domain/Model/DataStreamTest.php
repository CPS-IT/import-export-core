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

namespace CPSIT\ImportExportCore\Tests\Unit\Domain\Model;

use CPSIT\ImportExportCore\Domain\Model\DataStream;
use CPSIT\ImportExportCore\Domain\Model\DataStreamInterface;
use CPSIT\ImportExportCore\Stream\TemporaryStream;
use PHPUnit\Framework\TestCase;

class DataStreamTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(DataStreamInterface::class, new DataStream());
    }

    public function testStreamIsInitiallyEmpty(): void
    {
        $stream = (new DataStream())->getStream();

        self::assertSame(0, $stream->getSize());
        self::assertTrue($stream->isWritable());
    }

    public function testAcceptsStreamInConstructor(): void
    {
        $stream = new TemporaryStream('<rows/>');

        self::assertSame($stream, (new DataStream($stream))->getStream());
    }

    public function testSetsStream(): void
    {
        $subject = new DataStream();
        $stream = new TemporaryStream();

        $subject->setStream($stream);

        self::assertSame($stream, $subject->getStream());
    }

    public function testBufferIsInitiallyNull(): void
    {
        self::assertNull((new DataStream())->getStreamBuffer());
    }

    public function testSetsAndResetsBuffer(): void
    {
        $subject = new DataStream();

        $subject->setStreamBuffer('<rows/>');
        self::assertSame('<rows/>', $subject->getStreamBuffer());

        $subject->setStreamBuffer(null);
        self::assertNull($subject->getStreamBuffer());
    }

    public function testBufferIsWrittenToStream(): void
    {
        $subject = new DataStream();

        $subject->setStreamBuffer('<rows/>');

        self::assertSame('<rows/>', (string)$subject->getStream());
    }

    public function testBufferReturnsContentWrittenToStream(): void
    {
        $subject = new DataStream();

        $subject->getStream()->write('<row/>');
        $subject->getStream()->write('<row/>');

        self::assertSame('<row/><row/>', $subject->getStreamBuffer());
    }
}
