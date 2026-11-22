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

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\File\XmlSchema;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Tests\Unit\Resource\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XmlSchemaTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const int MISSING = 11;
    private const int INVALID = 12;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * Schema declaring `item` with the type `titleType`, which the named
     * schema has to define
     */
    private static function including(string $location, string $element = 'include'): string
    {
        return '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:' . $element . ' schemaLocation="' . htmlspecialchars($location) . '"/>'
            . '<xs:element name="item" type="titleType"/></xs:schema>';
    }

    private static function definingType(): string
    {
        return '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:complexType name="titleType"><xs:sequence><xs:element name="title" type="xs:string"/></xs:sequence></xs:complexType>'
            . '</xs:schema>';
    }

    private static function validates(XmlSchema $schema, string $uri, string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $document->loadXML($xml);

            return $schema->run(static fn (): bool => $document->schemaValidate($uri));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function fileLoader(array $files, string $base = ''): FileResourceLoader
    {
        $directory = $this->createTemporaryDirectory();
        foreach ($files as $path => $content) {
            if (!is_dir(\dirname($directory . '/' . $path))) {
                mkdir(\dirname($directory . '/' . $path), 0777, true);
            }
            file_put_contents($directory . '/' . $path, $content);
        }
        if (!is_dir($directory . '/' . $base)) {
            mkdir($directory . '/' . $base, 0777, true);
        }

        return new FileResourceLoader(new PathResolver($directory . '/' . $base));
    }

    public function testLoadsRelativeIncludeNextToTheSchema(): void
    {
        $schema = new XmlSchema($this->fileLoader([
            'schemas/main.xsd' => self::including('types/inc.xsd'),
            'schemas/types/inc.xsd' => self::definingType(),
        ]));

        $uri = $schema->load(['file' => 'schemas/main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title></item>'));
        self::assertFalse(self::validates($schema, $uri, '<item><name>a</name></item>'));
    }

    public function testResolvesParentDirectoryInsideTheBaseDirectory(): void
    {
        $schema = new XmlSchema($this->fileLoader([
            'schemas/main.xsd' => self::including('../types/inc.xsd'),
            'types/inc.xsd' => self::definingType(),
        ]));

        $uri = $schema->load(['file' => 'schemas/main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title></item>'));
    }

    public function testDoesNotIncludeSchemaOutsideOfTheBaseDirectory(): void
    {
        $schema = new XmlSchema($this->fileLoader([
            'base/main.xsd' => self::including('../outside.xsd'),
            'outside.xsd' => self::definingType(),
        ], 'base'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(self::MISSING);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testDoesNotIncludeSchemaByAbsolutePath(): void
    {
        $loader = $this->fileLoader(['inc.xsd' => self::definingType()]);
        $absolute = $this->temporaryDirectory . '/inc.xsd';
        file_put_contents($this->temporaryDirectory . '/main.xsd', self::including($absolute));
        $schema = new XmlSchema($loader);

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(self::MISSING);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function urlProvider(): array
    {
        return [
            'http' => ['http://127.0.0.1:1/inc.xsd'],
            'file' => ['file:///etc/hosts.xsd'],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testDoesNotIncludeUrlTheLoaderDoesNotSupport(string $url): void
    {
        $schema = new XmlSchema($this->fileLoader(['main.xsd' => self::including($url)]));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(self::MISSING);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testLoadsIncludedUrlWithTheResourceLoader(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader([
            'main.xsd' => self::including('https://example.org/inc.xsd'),
            'https://example.org/inc.xsd' => self::definingType(),
        ]));

        $uri = $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title></item>'));
    }

    public function testResolvesIncludeRelativeToUrl(): void
    {
        $loader = new InMemoryResourceLoader([
            'https://example.org/schemas/main.xsd' => self::including('../types/inc.xsd'),
            'https://example.org/types/inc.xsd' => self::definingType(),
        ]);
        $schema = new XmlSchema($loader);

        $uri = $schema->load(['url' => 'https://example.org/schemas/main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title></item>'));
    }

    public function testRejectsEmptySchemaLocation(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader(['main.xsd' => self::including(' ')]));

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(self::INVALID);
        $this->expectExceptionMessage('empty schemaLocation');
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testKeepsNetworkPathOnTheHostOfTheSchema(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader([
            'https://example.org/schemas/main.xsd' => self::including('//other.example/inc.xsd'),
            'https://example.org/other.example/inc.xsd' => self::definingType(),
        ]));

        $uri = $schema->load(['url' => 'https://example.org/schemas/main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title></item>'));
    }

    public function testRejectsIncludedFileOfOtherType(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader([
            'main.xsd' => self::including('inc.txt'),
            'inc.txt' => self::definingType(),
        ]));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(FileTypePolicy::ERROR_NOT_ALLOWED);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testLoadsImportAndCycles(): void
    {
        $other = '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:other">'
            . '<xs:include schemaLocation="more.xsd"/><xs:element name="note" type="xs:string"/></xs:schema>';
        $more = '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:other">'
            . '<xs:include schemaLocation="other.xsd"/><xs:element name="more" type="xs:string"/></xs:schema>';
        $main = '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:o="urn:other">'
            . '<xs:import namespace="urn:other" schemaLocation="./other.xsd"/>'
            . '<xs:element name="item"><xs:complexType><xs:sequence><xs:element ref="o:note"/></xs:sequence></xs:complexType></xs:element>'
            . '</xs:schema>';
        $schema = new XmlSchema(new InMemoryResourceLoader(['main.xsd' => $main, 'other.xsd' => $other, 'more.xsd' => $more]));

        $uri = $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><note xmlns="urn:other">a</note></item>'));
    }

    public function testLoadsRedefine(): void
    {
        $redefining = '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:redefine schemaLocation="inc.xsd"><xs:complexType name="titleType"><xs:complexContent>'
            . '<xs:extension base="titleType"><xs:sequence><xs:element name="subtitle" type="xs:string"/></xs:sequence></xs:extension>'
            . '</xs:complexContent></xs:complexType></xs:redefine>'
            . '<xs:element name="item" type="titleType"/></xs:schema>';
        $schema = new XmlSchema(new InMemoryResourceLoader(['main.xsd' => $redefining, 'inc.xsd' => self::definingType()]));

        $uri = $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);

        self::assertTrue(self::validates($schema, $uri, '<item><title>a</title><subtitle>b</subtitle></item>'));
    }

    public function testLimitsTheNumberOfSchemas(): void
    {
        $contents = [];
        for ($number = 0; $number <= XmlSchema::MAX_DOCUMENTS; $number++) {
            $contents['s' . $number . '.xsd'] = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
                . '<xs:include schemaLocation="s' . ($number + 1) . '.xsd"/></xs:schema>';
        }
        $schema = new XmlSchema(new InMemoryResourceLoader($contents));

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(self::INVALID);
        $schema->load(['file' => 's0.xsd'], self::MISSING, self::INVALID);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSchemaProvider(): array
    {
        return [
            'not well-formed' => ['<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'],
            'not a schema' => ['<notaschema/>'],
            'unknown type' => ['<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="a" type="unknown"/></xs:schema>'],
        ];
    }

    #[DataProvider('invalidSchemaProvider')]
    public function testRejectsInvalidSchema(string $content): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader(['main.xsd' => $content]));

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(self::INVALID);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testRejectsIncludedSchemaThatIsNotValid(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader([
            'main.xsd' => self::including('inc.xsd'),
            'inc.xsd' => '<notaschema/>',
        ]));

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(self::INVALID);
        $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
    }

    public function testRejectsSchemaWithExternalDtdOrEntitiesWithoutLoadingThem(): void
    {
        Fixtures\SpyStreamWrapper::register();
        try {
            $content = '<?xml version="1.0"?><!DOCTYPE xs:schema SYSTEM "spy://schema.dtd" [<!ENTITY e SYSTEM "spy://entity">]>'
                . '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:annotation><xs:documentation>&e;</xs:documentation></xs:annotation></xs:schema>';
            $schema = new XmlSchema(new InMemoryResourceLoader(['main.xsd' => $content]));

            try {
                $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
                self::fail('The schema was accepted.');
            } catch (ParseException $exception) {
                self::assertSame(self::INVALID, $exception->getCode());
            }

            self::assertSame([], Fixtures\SpyStreamWrapper::$opened);
        } finally {
            Fixtures\SpyStreamWrapper::unregister();
        }
    }

    public function testRestoresThePreviousEntityLoader(): void
    {
        $calls = 0;
        $loader = static function () use (&$calls) {
            $calls++;

            return null;
        };
        libxml_set_external_entity_loader($loader);
        try {
            $schema = new XmlSchema(new InMemoryResourceLoader([
                'main.xsd' => self::including('inc.xsd'),
                'inc.xsd' => self::definingType(),
            ]));
            $uri = $schema->load(['file' => 'main.xsd'], self::MISSING, self::INVALID);
            self::validates($schema, $uri, '<item><title>a</title></item>');

            self::assertSame($loader, libxml_get_external_entity_loader());
            self::assertSame(0, $calls);
        } finally {
            libxml_set_external_entity_loader(null);
        }
    }

    public function testRestoresThePreviousEntityLoaderAfterAnException(): void
    {
        $schema = new XmlSchema(new InMemoryResourceLoader());

        try {
            $schema->run(static function (): void {
                throw new \RuntimeException('test', 1);
            });
        } catch (\RuntimeException) {
        }

        self::assertNull(libxml_get_external_entity_loader());
    }
}
