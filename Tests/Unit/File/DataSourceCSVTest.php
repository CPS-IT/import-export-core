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

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\File\DataSourceCSV;
use CPSIT\ImportExportCore\Messaging\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataSourceCSVTest extends TestCase
{
    private InMemoryResourceLoader $loader;

    private DataSourceCSV $subject;

    protected function setUp(): void
    {
        $this->loader = new InMemoryResourceLoader();
        $this->subject = new DataSourceCSV($this->loader);
    }

    public function testReadsRecordsWithFieldNamesFromFirstRow(): void
    {
        $this->loader->set('a.csv', "uid,title\n1,One\n2,\"Two, with comma\"\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One'], ['uid' => '2', 'title' => 'Two, with comma']],
            $this->read(['file' => 'a.csv'])
        );
    }

    public function testReadsValuesWithLineBreaks(): void
    {
        $this->loader->set('a.csv', "uid,text\n1,\"line 1\nline 2\"\n2,b\n");

        self::assertSame(
            [['uid' => '1', 'text' => "line 1\nline 2"], ['uid' => '2', 'text' => 'b']],
            $this->read(['file' => 'a.csv'])
        );
    }

    public function testUsesConfiguredFieldsForAllRows(): void
    {
        $this->loader->set('a.csv', "1,One\n2,Two\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One'], ['uid' => '2', 'title' => 'Two']],
            $this->read(['file' => 'a.csv', 'fields' => ['uid', 'title']])
        );
    }

    public function testSkipsHeaderRowWithConfiguredFields(): void
    {
        $this->loader->set('a.csv', "Id,Title\n1,One\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One']],
            $this->read(['file' => 'a.csv', 'fields' => 'uid, title', 'header' => true])
        );
    }

    public function testUsesColumnIndexesWithoutHeaderAndFields(): void
    {
        $this->loader->set('a.csv', "1,One\n");

        self::assertSame([['0' => '1', '1' => 'One']], $this->read(['file' => 'a.csv', 'header' => false]));
    }

    public function testUsesConfiguredFormat(): void
    {
        $this->loader->set('a.csv', "uid;title\n1;'One; quoted'\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One; quoted']],
            $this->read(['file' => 'a.csv', 'delimiter' => ';', 'enclosure' => "'", 'escape' => ''])
        );
    }

    public function testRemovesByteOrderMarkAndSkipsEmptyRows(): void
    {
        $this->loader->set('a.csv', "\xEF\xBB\xBFuid,title\n\n1,One\n\n\n2,Two\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One'], ['uid' => '2', 'title' => 'Two']],
            $this->read(['file' => 'a.csv'])
        );
    }

    public function testConvertsEncodingToUtf8(): void
    {
        $this->loader->set('a.csv', (string)mb_convert_encoding("title\nKäse\n", 'ISO-8859-1', 'UTF-8'));

        self::assertSame([['title' => 'Käse']], $this->read(['file' => 'a.csv', 'encoding' => 'ISO-8859-1']));
    }

    public function testAddsRecordNumber(): void
    {
        $this->loader->set('a.csv', "title\nOne\n\nTwo\n");

        self::assertSame(
            [['title' => 'One', 'row' => 1], ['title' => 'Two', 'row' => 2]],
            $this->read(['file' => 'a.csv', 'recordNumberField' => 'row'])
        );
    }

    public function testSkipsAndReportsRowWithWrongNumberOfValues(): void
    {
        $this->loader->set('a.csv', "uid,title\n1,One\n2\n3,Three,extra\n4,Four\n");

        self::assertSame(
            [['uid' => '1', 'title' => 'One'], ['uid' => '4', 'title' => 'Four']],
            $this->read(['file' => 'a.csv'])
        );
        $messages = $this->subject->getAndPurgeMessages();
        self::assertCount(2, $messages);
        self::assertSame(DataSourceCSV::ERROR_INVALID_ROW, $messages[0]->getId());
        self::assertSame(Message::SEVERITY_ERROR, $messages[0]->getSeverity());
        self::assertStringContainsString('Row 3 of "a.csv" has 1 values', $messages[0]->getMessage());
        self::assertStringContainsString('Row 4 of "a.csv" has 3 values', $messages[1]->getMessage());
    }

    public function testThrowsForRowWithWrongNumberOfValuesInStrictMode(): void
    {
        $this->loader->set('a.csv', "uid,title\n1,One\n\n2\n");
        $records = [];

        try {
            foreach ($this->subject->getRecords(['file' => 'a.csv', 'strict' => true]) as $record) {
                $records[] = $record;
            }
            self::fail('No exception thrown');
        } catch (ParseException $exception) {
            self::assertSame(DataSourceCSV::ERROR_INVALID_ROW, $exception->getCode());
            self::assertSame('Row 4 of "a.csv" has 1 values, but there are 2 fields.', $exception->getMessage());
        }
        self::assertSame([['uid' => '1', 'title' => 'One']], $records);
        self::assertSame([], $this->subject->getAndPurgeMessages());
    }

    public function testEmptyFileHasNoRecords(): void
    {
        $this->loader->set('a.csv', '');

        self::assertSame([], $this->read(['file' => 'a.csv']));
        self::assertSame('', $this->subject->getCursor());
    }

    public function testReadsUrlWithLoader(): void
    {
        $this->loader->set('https://example.org/a.csv', "uid\n1\n");

        self::assertSame([['uid' => '1']], $this->read(['url' => 'https://example.org/a.csv']));
    }

    public function testThrowsForMissingResource(): void
    {
        $this->expectException(MissingResourceException::class);

        $this->read(['file' => 'missing.csv']);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function seekableProvider(): array
    {
        return ['seekable stream' => [true], 'stream not seekable' => [false]];
    }

    #[DataProvider('seekableProvider')]
    public function testResumesAfterCursor(bool $seekable): void
    {
        $content = "uid,title\n1,One\n\n2,\"Two\nlines\"\n3,Three\n4\n5,Five\n";
        $loader = new InMemoryResourceLoader(['a.csv' => $content], $seekable);
        $first = new DataSourceCSV($loader);
        $read = [];
        foreach ($first->getRecords(['file' => 'a.csv']) as $record) {
            $read[] = $record['uid'];
            if (\count($read) === 2) {
                break;
            }
        }
        $cursor = $first->getCursor();

        $second = new DataSourceCSV($loader);
        $second->resumeFrom($cursor);
        $records = iterator_to_array($second->getRecords(['file' => 'a.csv']), false);

        self::assertSame(['1', '2'], $read);
        self::assertSame(['3', '5'], array_column($records, 'uid'));
        self::assertStringContainsString('Row 6 of "a.csv"', $second->getAndPurgeMessages()[0]->getMessage());
    }

    public function testResumedRecordNumbersContinue(): void
    {
        $this->loader->set('a.csv', "uid\n1\n2\n3\n");
        $this->subject->resumeFrom('');
        $records = $this->read(['file' => 'a.csv', 'recordNumberField' => 'n']);
        self::assertSame([1, 2, 3], array_column($records, 'n'));

        $this->subject->resumeFrom('2:3:' . \strlen("uid\n1\n2\n"));

        self::assertSame([['uid' => '3', 'n' => 3]], $this->read(['file' => 'a.csv', 'recordNumberField' => 'n']));
    }

    public function testCursorIsKeptIfNoRecordFollows(): void
    {
        $this->loader->set('a.csv', "uid\n1\n");
        $this->subject->resumeFrom('1:2:6');

        self::assertSame([], $this->read(['file' => 'a.csv']));
        self::assertSame('1:2:6', $this->subject->getCursor());
    }

    public function testThrowsForInvalidCursor(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(DataSourceCSV::ERROR_INVALID_CURSOR);

        $this->subject->resumeFrom('abc');
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function configurationProvider(): array
    {
        return [
            'file' => [['file' => 'a.csv'], true],
            'url' => [['url' => 'https://example.org/a.csv'], true],
            'all options' => [[
                'file' => 'a.csv',
                'fields' => ['a', 'b'],
                'header' => true,
                'delimiter' => ';',
                'enclosure' => "'",
                'escape' => '',
                'encoding' => 'Windows-1252',
                'recordNumberField' => 'n',
            ], true],
            'no location' => [[], false],
            'file and url' => [['file' => 'a.csv', 'url' => 'https://example.org/a.csv'], false],
            'empty file' => [['file' => ''], false],
            'file not a string' => [['file' => ['a.csv']], false],
            'empty fields' => [['file' => 'a.csv', 'fields' => []], false],
            'empty field name' => [['file' => 'a.csv', 'fields' => 'a,,b'], false],
            'duplicate field' => [['file' => 'a.csv', 'fields' => ['a', 'a']], false],
            'fields map' => [['file' => 'a.csv', 'fields' => ['x' => 'a']], false],
            'long delimiter' => [['file' => 'a.csv', 'delimiter' => ';;'], false],
            'empty enclosure' => [['file' => 'a.csv', 'enclosure' => ''], false],
            'long escape' => [['file' => 'a.csv', 'escape' => '\\\\'], false],
            'header not bool' => [['file' => 'a.csv', 'header' => 'yes'], false],
            'encoding in lower case' => [['file' => 'a.csv', 'encoding' => 'iso-8859-15'], true],
            'ascii' => [['file' => 'a.csv', 'encoding' => 'ASCII'], true],
            'strict' => [['file' => 'a.csv', 'strict' => true], true],
            'unknown encoding' => [['file' => 'a.csv', 'encoding' => 'klingon'], false],
            'multi-byte encoding' => [['file' => 'a.csv', 'encoding' => 'UTF-16'], false],
            'encoding not a string' => [['file' => 'a.csv', 'encoding' => 8859], false],
            'strict not bool' => [['file' => 'a.csv', 'strict' => 1], false],
            'empty record number field' => [['file' => 'a.csv', 'recordNumberField' => ''], false],
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

    public function testThrowsForInvalidConfigurationWhenReading(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(DataSourceCSV::ERROR_INVALID_CONFIGURATION);

        $this->read(['file' => 'a.csv', 'delimiter' => '']);
    }

    /**
     * @param array<string, mixed> $configuration
     * @return list<array<string, mixed>>
     */
    private function read(array $configuration): array
    {
        return iterator_to_array($this->subject->getRecords($configuration), false);
    }
}
