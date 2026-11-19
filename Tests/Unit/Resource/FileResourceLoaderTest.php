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
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Stream\ResourceStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileResourceLoaderTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private FileResourceLoader $subject;

    protected function setUp(): void
    {
        $base = $this->createTemporaryDirectory();
        mkdir($base . '/data');
        file_put_contents($base . '/data/a.csv', "a,b\n1,2\n");
        $this->subject = new FileResourceLoader(new PathResolver($base));
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testOpensFile(): void
    {
        $stream = $this->subject->open('data/a.csv');

        self::assertInstanceOf(ResourceStream::class, $stream);
        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertSame("a,b\n1,2\n", $stream->getContents());
    }

    public function testSupportsPathsWithoutScheme(): void
    {
        self::assertTrue($this->subject->supports('data/a.csv'));
        self::assertFalse($this->subject->supports('https://example.org/a.csv'));
        self::assertFalse($this->subject->supports(''));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingResourceProvider(): array
    {
        return [
            'missing file' => ['data/missing.csv'],
            'directory' => ['data'],
            'outside of base directory' => ['../a.csv'],
            'url' => ['https://example.org/a.csv'],
        ];
    }

    #[DataProvider('missingResourceProvider')]
    public function testThrowsMissingResourceException(string $location): void
    {
        $this->expectException(MissingResourceException::class);

        $this->subject->open($location);
    }
}
