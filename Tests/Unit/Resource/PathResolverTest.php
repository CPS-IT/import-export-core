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

use CPSIT\ImportExportCore\Exception\InvalidArgumentException;
use CPSIT\ImportExportCore\Resource\PathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $base;

    protected function setUp(): void
    {
        $this->base = $this->createTemporaryDirectory();
        mkdir($this->base . '/data');
        file_put_contents($this->base . '/data/a.csv', 'a');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testResolvesRelativePathAgainstBaseDirectory(): void
    {
        $subject = new PathResolver($this->base);

        self::assertSame($this->base . '/data/a.csv', $subject->resolve('data/a.csv'));
        self::assertSame($this->base . '/data/a.csv', $subject->resolve('./data//a.csv'));
    }

    public function testResolvesPathOfFileNotYetCreated(): void
    {
        $subject = new PathResolver($this->base);

        self::assertSame($this->base . '/data/new/b.csv', $subject->resolve('data/new/b.csv'));
    }

    public function testDefaultBaseDirectoryIsWorkingDirectory(): void
    {
        self::assertSame(realpath((string)getcwd()), (new PathResolver())->getBaseDirectory());
    }

    public function testThrowsForMissingBaseDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(PathResolver::ERROR_INVALID_BASE_DIRECTORY);

        new PathResolver($this->base . '/missing');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notAllowedPathProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => ['  '],
            'only dots' => ['./.'],
            'absolute' => ['/etc/passwd'],
            'backslash absolute' => ['\\etc\\passwd'],
            'parent' => ['../a.csv'],
            'parent inside' => ['data/../../a.csv'],
            'parent with backslash' => ['data\\..\\..\\a.csv'],
            'stream wrapper' => ['php://filter/resource=data/a.csv'],
            'file scheme' => ['file:///etc/passwd'],
            'phar' => ['phar://data/a.phar'],
            'windows drive' => ['C:\\Windows'],
            'null byte' => ["data/a.csv\0.txt"],
        ];
    }

    #[DataProvider('notAllowedPathProvider')]
    public function testRejectsPath(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(PathResolver::ERROR_NOT_ALLOWED);

        (new PathResolver($this->base))->resolve($path);
    }

    public function testRejectsSymbolicLinkOutOfBaseDirectory(): void
    {
        $outside = $this->base . '/outside';
        mkdir($outside);
        file_put_contents($outside . '/secret.txt', 'secret');
        mkdir($this->base . '/root');
        symlink($outside, $this->base . '/root/link');
        $subject = new PathResolver($this->base . '/root');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(PathResolver::ERROR_NOT_ALLOWED);

        $subject->resolve('link/secret.txt');
    }

    public function testRejectsNewFileInSymbolicLinkOutOfBaseDirectory(): void
    {
        mkdir($this->base . '/outside');
        mkdir($this->base . '/root');
        symlink($this->base . '/outside', $this->base . '/root/link');
        $subject = new PathResolver($this->base . '/root');

        $this->expectException(InvalidArgumentException::class);

        $subject->resolve('link/new/file.csv');
    }

    public function testAllowsSymbolicLinkInsideBaseDirectory(): void
    {
        symlink($this->base . '/data', $this->base . '/alias');
        $subject = new PathResolver($this->base);

        self::assertSame($this->base . '/alias/a.csv', $subject->resolve('alias/a.csv'));
    }
}
