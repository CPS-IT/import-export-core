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

namespace CPSIT\ImportExportCore\Tests\Unit\Resource;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Resource\DelegatingResourceLoader;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;
use CPSIT\ImportExportCore\Stream\TemporaryStream;
use PHPUnit\Framework\TestCase;

class DelegatingResourceLoaderTest extends TestCase
{
    public function testOpensWithFirstSupportingLoader(): void
    {
        $stream = new TemporaryStream('b');
        $unsupported = $this->createMock(ResourceLoaderInterface::class);
        $unsupported->method('supports')->willReturn(false);
        $unsupported->expects(self::never())->method('open');
        $first = $this->createMock(ResourceLoaderInterface::class);
        $first->method('supports')->willReturn(true);
        $first->expects(self::once())->method('open')->with('x')->willReturn($stream);
        $second = $this->createMock(ResourceLoaderInterface::class);
        $second->method('supports')->willReturn(true);
        $second->expects(self::never())->method('open');

        $subject = new DelegatingResourceLoader($unsupported, $first, $second);

        self::assertTrue($subject->supports('x'));
        self::assertSame($stream, $subject->open('x'));
    }

    public function testThrowsIfNoLoaderSupportsLocation(): void
    {
        $subject = new DelegatingResourceLoader();

        self::assertFalse($subject->supports('x'));
        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(DelegatingResourceLoader::ERROR_NOT_SUPPORTED);

        $subject->open('x');
    }
}
