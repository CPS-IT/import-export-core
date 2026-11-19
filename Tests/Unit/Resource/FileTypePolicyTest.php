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

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileTypePolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function csvProvider(): array
    {
        return [
            'csv' => ['import/news.csv', true],
            'upper case' => ['import/NEWS.CSV', true],
            'tsv' => ['news.tsv', true],
            'xml' => ['news.xml', false],
            'no extension' => ['import/news', false],
            'php' => ['news.php', false],
            'php with csv extension' => ['news.php.csv', false],
            'phar' => ['news.phar', false],
            'directory' => ['import/', false],
        ];
    }

    #[DataProvider('csvProvider')]
    public function testAllowsConfiguredExtensions(string $path, bool $expected): void
    {
        self::assertSame($expected, (new FileTypePolicy(['csv', '.TSV']))->isAllowed($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deniedProvider(): array
    {
        return [
            'php' => ['a.php'],
            'php8' => ['a.PHP8'],
            'phtml' => ['dir/a.phtml'],
            'double extension' => ['a.php.txt'],
            'shtml' => ['a.shtml'],
            'cgi' => ['a.cgi'],
            'perl' => ['a.pl'],
            'htaccess' => ['dir/.htaccess'],
            'backslash' => ['dir\\a.php'],
            'control character' => ["a\n.csv"],
            'invalid UTF-8' => ["a\xff.csv"],
        ];
    }

    #[DataProvider('deniedProvider')]
    public function testDeniesExecutableFilesWithoutList(string $path): void
    {
        self::assertTrue(FileTypePolicy::isDenied($path));
        self::assertFalse((new FileTypePolicy())->isAllowed($path));
    }

    public function testAllowsOtherFilesWithoutList(): void
    {
        self::assertTrue((new FileTypePolicy())->isAllowed('dir/a.json'));
        self::assertTrue((new FileTypePolicy())->isAllowed('dir/php/a.csv'));
    }

    public function testAssertAllowedThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(FileTypePolicy::ERROR_NOT_ALLOWED);
        $this->expectExceptionMessage('Allowed: csv.');

        (new FileTypePolicy(['csv']))->assertAllowed('news.xml');
    }
}
