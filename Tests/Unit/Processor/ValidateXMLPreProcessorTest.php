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

namespace CPSIT\ImportExportCore\Tests\Unit\Processor;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Processor\ValidateXMLPreProcessor;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Tests\Unit\File\InMemoryResourceLoader;
use CPSIT\ImportExportCore\Tests\Unit\Resource\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from PreProcessor\ValidateXMLTest of t3import_export
 */
final class ValidateXMLPreProcessorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const string SCHEMA = <<<'XSD'
        <?xml version="1.0" encoding="utf-8"?>
        <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
          <xs:element name="item">
            <xs:complexType>
              <xs:sequence>
                <xs:element name="title" type="xs:string"/>
              </xs:sequence>
            </xs:complexType>
          </xs:element>
        </xs:schema>
        XSD;

    private InMemoryResourceLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new InMemoryResourceLoader(['schema.xsd' => self::SCHEMA, 'empty.xsd' => ' ']);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSubject(array $configuration): ValidateXMLPreProcessor
    {
        $subject = new ValidateXMLPreProcessor($this->loader);
        $subject->setConfiguration($configuration);

        return $subject;
    }

    /**
     * @param array<mixed> $information
     */
    private static function firstInformation(?array $information): string
    {
        self::assertIsArray($information);
        self::assertIsString($information[0] ?? null);

        return $information[0];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'fields empty' => [['fields' => '']];
        yield 'fields only separators' => [['fields' => ' , ']];
        yield 'fields empty list' => [['fields' => []]];
        yield 'fields map' => [['fields' => ['a' => 'foo']]];
        yield 'fields list with a number' => [['fields' => ['foo', 1]]];
        yield 'fields number' => [['fields' => 5]];
        yield 'identifier empty' => [['fields' => 'foo', 'identifier' => '']];
        yield 'identifier not a string' => [['fields' => 'foo', 'identifier' => 1]];
        yield 'schema string' => [['fields' => 'foo', 'schema' => 'schema.xsd']];
        yield 'schema without file or url' => [['fields' => 'foo', 'schema' => []]];
        yield 'schema with file and url' => [['fields' => 'foo', 'schema' => ['file' => 'a.xsd', 'url' => 'https://example.org/a.xsd']]];
        yield 'schema file empty' => [['fields' => 'foo', 'schema' => ['file' => ' ']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $subject = new ValidateXMLPreProcessor($this->loader);

        self::assertFalse($subject->isConfigurationValid($configuration));
        $this->expectException(InvalidConfigurationException::class);
        $subject->setConfiguration($configuration);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validConfigurationProvider(): iterable
    {
        yield 'fields string' => [['fields' => 'foo']];
        yield 'fields comma-separated' => [['fields' => 'foo, bar']];
        yield 'fields list' => [['fields' => ['foo', 'bar']]];
        yield 'identifier' => [['fields' => 'foo', 'identifier' => 'id']];
        yield 'schema file' => [['fields' => 'foo', 'schema' => ['file' => 'schema.xsd']]];
        yield 'schema url' => [['fields' => 'foo', 'schema' => ['url' => 'https://example.org/schema.xsd']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('validConfigurationProvider')]
    public function testAcceptsValidConfiguration(array $configuration): void
    {
        self::assertTrue((new ValidateXMLPreProcessor($this->loader))->isConfigurationValid($configuration));
    }

    public function testKeepsWellFormedXml(): void
    {
        $subject = $this->createSubject(['fields' => 'foo']);
        $record = ['uid' => 1, 'foo' => '<item><title>a</title></item>'];

        self::assertSame($record, $subject->process($record));
        self::assertSame([], $subject->getMessages());
    }

    public function testAcceptsDomDocument(): void
    {
        $document = new \DOMDocument();
        $document->loadXML('<item><title>a</title></item>');
        $record = ['uid' => 1, 'foo' => $document];

        $result = $this->createSubject(['fields' => 'foo'])->process($record);

        self::assertArrayNotHasKey(ValidateXMLPreProcessor::KEY_VALIDATION_FAILED, $result);
    }

    public function testMarksRecordAndLogsErrorForMalformedXml(): void
    {
        $subject = $this->createSubject(['fields' => 'foo']);

        $result = $subject->process(['uid' => 7, 'foo' => '<item><title>a</item>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertTrue($subject->hasMessageWithId(ValidateXMLPreProcessor::ERROR_INVALID_XML));
        $information = self::firstInformation($subject->getMessages()[0]->getAdditionalInformation());
        self::assertStringContainsString('Error validating content of field foo', $information);
        self::assertStringContainsString('Record ID 7', $information);
        self::assertStringContainsString('Message: ', $information);
    }

    public function testUsesConfiguredIdentifierField(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'identifier' => 'id']);

        $subject->process(['id' => 'abc', 'foo' => '<item>']);

        self::assertStringContainsString(
            'Record ID abc',
            self::firstInformation($subject->getMessages()[0]->getAdditionalInformation())
        );
    }

    public function testLogsEmptyRecordIdIfIdentifierFieldIsMissing(): void
    {
        $subject = $this->createSubject(['fields' => 'foo']);

        $subject->process(['foo' => '<item>']);

        self::assertStringContainsString(
            "Record ID \n",
            self::firstInformation($subject->getMessages()[0]->getAdditionalInformation())
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyValueProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace' => ['  '];
        yield 'integer' => [5];
        yield 'null' => [null];
        yield 'array' => [['<a/>']];
    }

    #[DataProvider('emptyValueProvider')]
    public function testMarksRecordForEmptyValueWithoutMessage(mixed $value): void
    {
        $subject = $this->createSubject(['fields' => 'foo']);

        $result = $subject->process(['uid' => 1, 'foo' => $value]);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertSame([], $subject->getMessages());
    }

    public function testMarksRecordForMissingFieldWithoutMessage(): void
    {
        $subject = $this->createSubject(['fields' => 'foo']);

        $result = $subject->process(['uid' => 1]);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertSame([], $subject->getMessages());
    }

    public function testStopsAtFirstInvalidField(): void
    {
        $subject = $this->createSubject(['fields' => 'foo, bar']);

        $result = $subject->process(['uid' => 1, 'foo' => '<a>', 'bar' => '<b>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertNotSame([], $subject->getMessages());
        foreach ($subject->getMessages() as $message) {
            $information = self::firstInformation($message->getAdditionalInformation());
            self::assertStringContainsString('field foo', $information);
        }
    }

    public function testValidatesAllFields(): void
    {
        $subject = $this->createSubject(['fields' => ['foo', 'bar']]);

        $result = $subject->process(['uid' => 1, 'foo' => '<a/>', 'bar' => '<b>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertStringContainsString(
            'field bar',
            self::firstInformation($subject->getMessages()[0]->getAdditionalInformation())
        );
    }

    public function testAcceptsXmlMatchingSchema(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'schema.xsd']]);
        $record = ['uid' => 1, 'foo' => '<item><title>a</title></item>'];

        self::assertSame($record, $subject->process($record));
    }

    public function testRejectsXmlNotMatchingSchema(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'schema.xsd']]);

        $result = $subject->process(['uid' => 1, 'foo' => '<item><name>a</name></item>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
        self::assertTrue($subject->hasMessageWithId(ValidateXMLPreProcessor::ERROR_INVALID_XML));
    }

    public function testLoadsSchemaOncePerTask(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'schema.xsd']]);
        $subject->process(['uid' => 1, 'foo' => '<item><title>a</title></item>']);
        $this->loader->set('schema.xsd', '');

        $result = $subject->process(['uid' => 2, 'foo' => '<item><title>b</title></item>']);

        self::assertArrayNotHasKey(ValidateXMLPreProcessor::KEY_VALIDATION_FAILED, $result);
    }

    public function testLoadsSchemaFromUrl(): void
    {
        $this->loader->set('https://example.org/schema.xsd', self::SCHEMA);
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['url' => 'https://example.org/schema.xsd']]);

        $result = $subject->process(['uid' => 1, 'foo' => '<item><name>a</name></item>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
    }

    public function testThrowsExceptionIfSchemaIsEmpty(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'empty.xsd']]);

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(ValidateXMLPreProcessor::ERROR_MISSING_SCHEMA);
        $subject->process(['uid' => 1, 'foo' => '<item/>']);
    }

    public function testThrowsExceptionIfSchemaDoesNotExist(): void
    {
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'missing.xsd']]);

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(ValidateXMLPreProcessor::ERROR_MISSING_SCHEMA);
        $subject->process(['uid' => 1, 'foo' => '<item/>']);
    }

    public function testThrowsExceptionIfSchemaFileTypeIsNotAllowed(): void
    {
        $this->loader->set('schema.txt', self::SCHEMA);
        $subject = $this->createSubject(['fields' => 'foo', 'schema' => ['file' => 'schema.txt']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(FileTypePolicy::ERROR_NOT_ALLOWED);
        $subject->process(['uid' => 1, 'foo' => '<item/>']);
    }

    public function testAcceptsOtherSchemaFileTypesFromTheApplication(): void
    {
        $this->loader->set('schema.txt', self::SCHEMA);
        $subject = new ValidateXMLPreProcessor($this->loader, new FileTypePolicy(['txt']));
        $subject->setConfiguration(['fields' => 'foo', 'schema' => ['file' => 'schema.txt']]);
        $record = ['uid' => 1, 'foo' => '<item><title>a</title></item>'];

        self::assertSame($record, $subject->process($record));
    }

    public function testDoesNotReadSchemaOutsideOfBaseDirectory(): void
    {
        $directory = $this->createTemporaryDirectory();
        mkdir($directory . '/base');
        file_put_contents($directory . '/schema.xsd', self::SCHEMA);
        $subject = new ValidateXMLPreProcessor(new FileResourceLoader(new PathResolver($directory . '/base')));
        $subject->setConfiguration(['fields' => 'foo', 'schema' => ['file' => '../schema.xsd']]);

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(ValidateXMLPreProcessor::ERROR_MISSING_SCHEMA);
        $subject->process(['uid' => 1, 'foo' => '<item/>']);
    }

    public function testReadsSchemaFileWithDefaultLoader(): void
    {
        $directory = $this->createTemporaryDirectory();
        file_put_contents($directory . '/schema.xsd', self::SCHEMA);
        $subject = new ValidateXMLPreProcessor(new FileResourceLoader(new PathResolver($directory)));
        $subject->setConfiguration(['fields' => 'foo', 'schema' => ['file' => 'schema.xsd']]);

        $result = $subject->process(['uid' => 1, 'foo' => '<item><name>a</name></item>']);

        self::assertTrue($result[ValidateXMLPreProcessor::KEY_VALIDATION_FAILED]);
    }

    public function testRestoresLibxmlErrorHandling(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            $this->createSubject(['fields' => 'foo'])->process(['uid' => 1, 'foo' => '<a>']);

            self::assertFalse(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
