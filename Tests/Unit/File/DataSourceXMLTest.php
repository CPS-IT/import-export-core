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
use CPSIT\ImportExportCore\File\DataSourceXML;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Tests\Unit\Resource\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataSourceXMLTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const string NEWS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns:media="http://example.org/media">
          <item id="1" type="news">
            <title>One</title>
            <tag>a</tag>
            <tag>b</tag>
            <tag>c</tag>
            <body><![CDATA[<p>Text</p>]]></body>
            <empty/>
            <link href="/one">Read more</link>
            <media:image>one.jpg</media:image>
          </item>
          <item id="2">
            <title>Two</title>
            <tag>x</tag>
          </item>
          <other><item id="3"><title>Three</title></item></other>
        </feed>
        XML;

    private InMemoryResourceLoader $loader;

    private DataSourceXML $subject;

    protected function setUp(): void
    {
        $this->loader = new InMemoryResourceLoader(['news.xml' => self::NEWS]);
        $this->subject = new DataSourceXML($this->loader);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function modeProvider(): array
    {
        return [
            'element' => [['element' => 'item']],
            'expression' => [['expression' => '//item']],
        ];
    }

    /**
     * @param array<string, mixed> $mode
     */
    #[DataProvider('modeProvider')]
    public function testConvertsElementsIntoRecords(array $mode): void
    {
        $records = $this->read(['file' => 'news.xml'] + $mode);

        self::assertCount(3, $records);
        self::assertSame(
            [
                '@attributes' => ['id' => '1', 'type' => 'news'],
                'title' => 'One',
                'tag' => ['a', 'b', 'c'],
                'body' => '<p>Text</p>',
                'empty' => '',
                'link' => ['@attributes' => ['href' => '/one'], '@value' => 'Read more'],
                'image' => 'one.jpg',
            ],
            $records[0]
        );
        self::assertSame(['@attributes' => ['id' => '2'], 'title' => 'Two', 'tag' => 'x'], $records[1]);
        self::assertSame(['@attributes' => ['id' => '3'], 'title' => 'Three'], $records[2]);
    }

    public function testSelectsElementsWithExpression(): void
    {
        $records = $this->read(['file' => 'news.xml', 'expression' => '/feed/item[@type="news"]/title | //other']);

        self::assertSame([['@value' => 'One'], ['item' => ['@attributes' => ['id' => '3'], 'title' => 'Three']]], $records);
    }

    public function testSkipsNodesThatAreNoElements(): void
    {
        self::assertSame([], $this->read(['file' => 'news.xml', 'expression' => '//item/@id']));
    }

    public function testMatchesElementsByNamespace(): void
    {
        $xml = '<root xmlns="http://example.org/a" xmlns:b="http://example.org/b">'
            . '<item>a1</item><b:item>b1</b:item><item>a2</item></root>';
        $this->loader->set('ns.xml', $xml);
        $namespaces = ['b' => 'http://example.org/b', 'a' => 'http://example.org/a'];

        self::assertSame(
            [['@value' => 'b1']],
            $this->read(['file' => 'ns.xml', 'element' => 'b:item', 'namespaces' => $namespaces])
        );
        self::assertSame(
            [['@value' => 'a1'], ['@value' => 'a2']],
            $this->read(['file' => 'ns.xml', 'expression' => '/a:root/a:item', 'namespaces' => $namespaces])
        );
        self::assertCount(3, $this->read(['file' => 'ns.xml', 'element' => 'item']));
    }

    public function testAddsRecordNumber(): void
    {
        $records = $this->read(['file' => 'news.xml', 'element' => 'title', 'recordNumberField' => 'n']);

        self::assertSame([1, 2, 3], array_column($records, 'n'));
    }

    #[DataProvider('modeProvider')]
    public function testResumesAfterCursor(array $mode): void
    {
        $first = new DataSourceXML($this->loader);
        foreach ($first->getRecords(['file' => 'news.xml'] + $mode) as $record) {
            break;
        }
        self::assertSame('1', $first->getCursor());

        $second = new DataSourceXML($this->loader);
        $second->resumeFrom($first->getCursor());
        $records = iterator_to_array($second->getRecords(['file' => 'news.xml'] + $mode), false);

        self::assertSame(['2', '3'], array_map(static fn (array $record): string => $record['@attributes']['id'], $records));
        self::assertSame('3', $second->getCursor());
    }

    public function testThrowsForInvalidCursor(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(DataSourceXML::ERROR_INVALID_CURSOR);

        $this->subject->resumeFrom('1:2');
    }

    public function testReadsLocalFileByPath(): void
    {
        $directory = $this->createTemporaryDirectory();
        file_put_contents($directory . '/news.xml', self::NEWS);
        $subject = new DataSourceXML(new FileResourceLoader(new PathResolver($directory)));

        $records = iterator_to_array($subject->getRecords(['file' => 'news.xml', 'element' => 'item']), false);

        self::assertCount(3, $records);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function invalidXmlProvider(): array
    {
        return [
            'element, broken' => ['<feed><item>a</item><item>', ['element' => 'item']],
            'element, empty' => ['', ['element' => 'item']],
            'expression, broken' => ['<feed><item>a</item><item>', ['expression' => '//item']],
            'expression, empty' => ['', ['expression' => '//item']],
        ];
    }

    /**
     * @param array<string, mixed> $mode
     */
    #[DataProvider('invalidXmlProvider')]
    public function testThrowsForInvalidXml(string $xml, array $mode): void
    {
        $this->loader->set('broken.xml', $xml);

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(DataSourceXML::ERROR_INVALID_XML);

        $this->read(['file' => 'broken.xml'] + $mode);
    }

    public function testThrowsForInvalidExpression(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionCode(DataSourceXML::ERROR_INVALID_XML);

        $this->read(['file' => 'news.xml', 'expression' => '//item[']);
    }

    #[DataProvider('modeProvider')]
    public function testDoesNotExpandExternalEntities(array $mode): void
    {
        $directory = $this->createTemporaryDirectory();
        file_put_contents($directory . '/secret.txt', 'secret');
        $xml = '<?xml version="1.0"?><!DOCTYPE feed [<!ENTITY secret SYSTEM "file://' . $directory . '/secret.txt">]>'
            . '<feed><item><title>&secret;</title></item></feed>';
        $this->loader->set('xxe.xml', $xml);

        $records = $this->read(['file' => 'xxe.xml'] + $mode);

        self::assertStringNotContainsString('secret', (string)json_encode($records));
    }

    public function testThrowsForMissingResource(): void
    {
        $this->expectException(MissingResourceException::class);

        $this->read(['file' => 'missing.xml', 'element' => 'item']);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function configurationProvider(): array
    {
        return [
            'element' => [['file' => 'a.xml', 'element' => 'item'], true],
            'expression' => [['url' => 'https://example.org/a.xml', 'expression' => '//item'], true],
            'element with prefix' => [['file' => 'a.xml', 'element' => 'b:item', 'namespaces' => ['b' => 'urn:b'], 'recordNumberField' => 'n'], true],
            'no location' => [['element' => 'item'], false],
            'file and url' => [['file' => 'a.xml', 'url' => 'https://example.org/a.xml', 'element' => 'item'], false],
            'neither element nor expression' => [['file' => 'a.xml'], false],
            'element and expression' => [['file' => 'a.xml', 'element' => 'item', 'expression' => '//item'], false],
            'invalid element name' => [['file' => 'a.xml', 'element' => '1item'], false],
            'element not a string' => [['file' => 'a.xml', 'element' => ['item']], false],
            'unknown prefix' => [['file' => 'a.xml', 'element' => 'b:item'], false],
            'two prefixes' => [['file' => 'a.xml', 'element' => 'a:b:item', 'namespaces' => ['a' => 'urn:a']], false],
            'empty expression' => [['file' => 'a.xml', 'expression' => ' '], false],
            'namespaces not a map' => [['file' => 'a.xml', 'element' => 'item', 'namespaces' => ['urn:a']], false],
            'empty namespace uri' => [['file' => 'a.xml', 'element' => 'item', 'namespaces' => ['a' => '']], false],
            'empty record number field' => [['file' => 'a.xml', 'element' => 'item', 'recordNumberField' => ''], false],
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
        $this->expectExceptionCode(DataSourceXML::ERROR_INVALID_CONFIGURATION);

        $this->read(['file' => 'news.xml']);
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
