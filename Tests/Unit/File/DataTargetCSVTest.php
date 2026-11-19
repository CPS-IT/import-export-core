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

namespace CPSIT\ImportExportCore\Tests\Unit\File;

use CPSIT\ImportExportCore\Exception\PersistenceException;
use CPSIT\ImportExportCore\File\DataSourceCSV;
use CPSIT\ImportExportCore\File\DataTargetCSV;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Tests\Unit\Resource\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTargetCSVTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $directory;

    private DataTargetCSV $subject;

    protected function setUp(): void
    {
        $this->directory = $this->createTemporaryDirectory();
        $this->subject = new DataTargetCSV(new PathResolver($this->directory));
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testWritesRecordsWithHeaderOfFirstRecord(): void
    {
        $this->write(['file' => 'out/a.csv'], [
            ['uid' => 1, 'title' => 'One, "quoted"'],
            ['title' => "Two\nlines", 'uid' => 2, 'other' => 'x'],
        ]);

        self::assertSame("uid,title\n1,\"One, \"\"quoted\"\"\"\n2,\"Two\nlines\"\n", $this->content('out/a.csv'));
    }

    public function testWritesConfiguredFieldsAndFormat(): void
    {
        $this->write(
            ['file' => 'a.csv', 'fields' => 'title, uid', 'delimiter' => ';', 'enclosure' => "'", 'escape' => '', 'header' => false, 'bom' => true],
            [['uid' => 1, 'title' => 'a;b'], ['uid' => 2]]
        );

        self::assertSame("\xEF\xBB\xBF'a;b';1\n;2\n", $this->content('a.csv'));
    }

    public function testConvertsValuesToStrings(): void
    {
        $stringable = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'object';
            }
        };
        $this->write(['file' => 'a.csv', 'header' => false], [
            ['a' => null, 'b' => true, 'c' => false, 'd' => 1.5, 'e' => ['x' => 'ü'], 'f' => $stringable],
        ]);

        self::assertSame(",1,0,1.5,\"{\"\"x\"\":\"\"ü\"\"}\",object\n", $this->content('a.csv'));
    }

    public function testWritesHeaderOnlyFileWithoutRecords(): void
    {
        $this->subject->setConfiguration(['file' => 'a.csv', 'fields' => ['uid', 'title']]);
        $this->subject->persistAll(null, $this->subject->getConfiguration());

        self::assertSame("uid,title\n", $this->content('a.csv'));
    }

    public function testWritesNoFileWithoutRecordsAndFields(): void
    {
        $this->subject->setConfiguration(['file' => 'a.csv']);
        $this->subject->persistAll(null, $this->subject->getConfiguration());

        self::assertFileDoesNotExist($this->directory . '/a.csv');
    }

    public function testReplacesFileOnNextRun(): void
    {
        $this->write(['file' => 'a.csv'], [['uid' => 1], ['uid' => 2]]);
        $this->write(['file' => 'a.csv'], [['uid' => 3]]);

        self::assertSame("uid\n3\n", $this->content('a.csv'));
    }

    public function testWrittenFileCanBeReadBySource(): void
    {
        $records = [['uid' => '1', 'title' => "Käse, \"alt\"\nzwei"], ['uid' => '2', 'title' => '']];
        $this->write(['file' => 'a.csv', 'bom' => true], $records);

        $source = new DataSourceCSV(new FileResourceLoader(new PathResolver($this->directory)));

        self::assertSame($records, iterator_to_array($source->getRecords(['file' => 'a.csv']), false));
    }

    public function testThrowsForObjectRecord(): void
    {
        $this->subject->setConfiguration(['file' => 'a.csv']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(DataTargetCSV::ERROR_INVALID_RECORD);

        $this->subject->persist(new \stdClass(), $this->subject->getConfiguration());
    }

    public function testThrowsForPathOutsideOfBaseDirectory(): void
    {
        $this->subject->setConfiguration(['file' => '../a.csv']);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionCode(DataTargetCSV::ERROR_WRITE_FAILED);

        $this->subject->persist(['uid' => 1], $this->subject->getConfiguration());
    }

    public function testThrowsIfFileCannotBeCreated(): void
    {
        file_put_contents($this->directory . '/file', '');
        $this->subject->setConfiguration(['file' => 'file/a.csv']);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionCode(DataTargetCSV::ERROR_WRITE_FAILED);

        $this->subject->persist(['uid' => 1], $this->subject->getConfiguration());
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function configurationProvider(): array
    {
        return [
            'file' => [['file' => 'a.csv'], true],
            'all options' => [['file' => 'a.csv', 'fields' => ['a'], 'header' => false, 'delimiter' => ';', 'enclosure' => "'", 'escape' => '', 'bom' => true], true],
            'no file' => [[], false],
            'empty file' => [['file' => ' '], false],
            'url' => [['url' => 'https://example.org/a.csv'], false],
            'header not bool' => [['file' => 'a.csv', 'header' => 1], false],
            'bom not bool' => [['file' => 'a.csv', 'bom' => 'yes'], false],
            'invalid delimiter' => [['file' => 'a.csv', 'delimiter' => ''], false],
            'invalid fields' => [['file' => 'a.csv', 'fields' => []], false],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('configurationProvider')]
    public function testValidatesConfiguration(array $configuration, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isConfigurationValid($configuration));
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<array<string, mixed>> $records
     */
    private function write(array $configuration, array $records): void
    {
        $this->subject->setConfiguration($configuration);
        foreach ($records as $record) {
            self::assertTrue($this->subject->persist($record, $configuration));
        }
        $this->subject->persistAll(null, $configuration);
    }

    private function content(string $file): string
    {
        return (string)file_get_contents($this->directory . '/' . $file);
    }
}
