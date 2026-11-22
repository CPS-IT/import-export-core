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

use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\File\ValidateXMLFinisher;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Pipeline\TaskContext;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\NullCheckpointStore;
use CPSIT\ImportExportCore\State\NullIdMap;
use CPSIT\ImportExportCore\Tests\Unit\File\Fixtures\SpyStreamWrapper;
use CPSIT\ImportExportCore\Tests\Unit\Resource\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases from Finisher\ValidateXMLTest of t3import_export
 */
final class ValidateXMLFinisherTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const string SCHEMA = <<<'XSD'
        <?xml version="1.0" encoding="utf-8"?>
        <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
          <xs:element name="items">
            <xs:complexType>
              <xs:sequence>
                <xs:element name="item" type="xs:string" maxOccurs="unbounded"/>
              </xs:sequence>
            </xs:complexType>
          </xs:element>
        </xs:schema>
        XSD;

    private const string VALID = '<?xml version="1.0"?><items><item>a</item><item>b</item></items>';

    private InMemoryResourceLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new InMemoryResourceLoader(['schema.xsd' => self::SCHEMA, 'export.xml' => self::VALID]);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function finish(array $configuration, ?ValidateXMLFinisher $subject = null): ValidateXMLFinisher
    {
        $subject ??= new ValidateXMLFinisher($this->loader);
        $subject->setConfiguration($configuration);
        $subject->finish(new TaskContext(
            'task',
            new TransferTask(),
            new TaskResult(),
            new NullIdMap(),
            new NullCheckpointStore(),
            new Checkpoint('task')
        ));

        return $subject;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'file and url' => [['file' => 'a.xml', 'url' => 'https://example.org/a.xml']];
        yield 'file empty' => [['file' => '']];
        yield 'file not a string' => [['file' => ['a.xml']]];
        yield 'schema string' => [['file' => 'a.xml', 'schema' => 'schema.xsd']];
        yield 'schema without file or url' => [['file' => 'a.xml', 'schema' => ['foo' => 'bar']]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $subject = new ValidateXMLFinisher($this->loader);

        self::assertFalse($subject->isConfigurationValid($configuration));
        $this->expectException(InvalidConfigurationException::class);
        $subject->setConfiguration($configuration);
    }

    public function testAcceptsValidConfiguration(): void
    {
        $subject = new ValidateXMLFinisher($this->loader);

        self::assertTrue($subject->isConfigurationValid(['file' => 'export.xml']));
        self::assertTrue($subject->isConfigurationValid(['url' => 'https://example.org/export.xml', 'schema' => ['url' => 'https://example.org/schema.xsd']]));
    }

    public function testLogsNoticeForValidFile(): void
    {
        $subject = $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']]);

        $messages = $subject->getMessages();
        self::assertCount(1, $messages);
        self::assertSame(ValidateXMLFinisher::NOTICE_VALID, $messages[0]->getId());
        self::assertSame(Message::SEVERITY_NOTICE, $messages[0]->getSeverity());
    }

    public function testLogsNoticeForWellFormedFileWithoutSchema(): void
    {
        $this->loader->set('export.xml', '<other/>');

        $subject = $this->finish(['file' => 'export.xml']);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_VALID));
    }

    public function testLogsNoticeWithErrorsForFileNotMatchingSchema(): void
    {
        $this->loader->set('export.xml', '<items><item>a</item><other/><foo/></items>');

        $subject = $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']]);

        $messages = $subject->getMessages();
        self::assertCount(1, $messages);
        self::assertSame(ValidateXMLFinisher::NOTICE_INVALID, $messages[0]->getId());
        self::assertSame(Message::SEVERITY_NOTICE, $messages[0]->getSeverity());
        $information = $messages[0]->getAdditionalInformation();
        self::assertIsArray($information);
        self::assertNotSame([], $information);
        self::assertIsString($information[0]);
        self::assertStringContainsString('Line 1', $information[0]);
        self::assertStringStartsWith(\sprintf('XML is invalid. There %s', \count($information) > 1 ? 'were' : 'was'), $messages[0]->getMessage());
    }

    public function testClonesValidateWithTheirOwnSchema(): void
    {
        $this->loader->set('other.xsd', str_replace('"item"', '"entry"', self::SCHEMA));
        $this->loader->set('other.xml', '<items><entry>a</entry></items>');
        $prototype = new ValidateXMLFinisher($this->loader);
        $first = clone $prototype;
        $second = clone $prototype;

        $this->finish(['file' => 'other.xml', 'schema' => ['file' => 'other.xsd']], $second);
        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']], $first);
        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'other.xsd']], $second);

        self::assertSame([ValidateXMLFinisher::NOTICE_VALID], array_map(static fn (Message $message): int => $message->getId(), $first->getMessages()));
        self::assertSame(
            [ValidateXMLFinisher::NOTICE_VALID, ValidateXMLFinisher::NOTICE_INVALID],
            array_map(static fn (Message $message): int => $message->getId(), $second->getMessages())
        );
    }

    public function testCountsSingleError(): void
    {
        $this->loader->set('export.xml', '<items><item>a</item>');

        $subject = $this->finish(['file' => 'export.xml']);

        $message = $subject->getMessages()[0];
        self::assertSame(ValidateXMLFinisher::NOTICE_INVALID, $message->getId());
        self::assertCount(1, (array)$message->getAdditionalInformation());
        self::assertStringStartsWith('XML is invalid. There was 1 error.', $message->getMessage());
    }

    public function testLogsNoticeForMalformedFile(): void
    {
        $this->loader->set('export.xml', '<items><item>a</items>');

        $subject = $this->finish(['file' => 'export.xml']);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_INVALID));
    }

    public function testLogsErrorForMissingFile(): void
    {
        $subject = $this->finish(['file' => 'missing.xml']);

        $messages = $subject->getMessages();
        self::assertCount(1, $messages);
        self::assertSame(ValidateXMLFinisher::ERROR_EMPTY_RESOURCE, $messages[0]->getId());
        self::assertSame(Message::SEVERITY_ERROR, $messages[0]->getSeverity());
    }

    public function testLogsErrorForEmptyFile(): void
    {
        $this->loader->set('export.xml', '');

        $subject = $this->finish(['file' => 'export.xml']);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::ERROR_EMPTY_RESOURCE));
    }

    public function testThrowsExceptionForInvalidSchema(): void
    {
        $this->loader->set('schema.xsd', '<foo/>');

        $this->expectException(ParseException::class);
        $this->expectExceptionCode(ValidateXMLFinisher::ERROR_INVALID_SCHEMA);

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']]);
    }

    public function testLoadsIncludeRelativeToTheSchema(): void
    {
        $directory = $this->createTemporaryDirectory();
        mkdir($directory . '/schemas');
        file_put_contents($directory . '/export.xml', self::VALID);
        file_put_contents($directory . '/schemas/main.xsd', '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:include schemaLocation="inc.xsd"/></xs:schema>');
        file_put_contents($directory . '/schemas/inc.xsd', self::SCHEMA);
        $subject = new ValidateXMLFinisher(new FileResourceLoader(new PathResolver($directory)));

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schemas/main.xsd']], $subject);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_VALID));
    }

    public function testThrowsExceptionForIncludeOutsideOfTheBaseDirectory(): void
    {
        $directory = $this->createTemporaryDirectory();
        mkdir($directory . '/base');
        file_put_contents($directory . '/inc.xsd', self::SCHEMA);
        file_put_contents($directory . '/base/export.xml', self::VALID);
        file_put_contents($directory . '/base/main.xsd', '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            . '<xs:include schemaLocation="../inc.xsd"/></xs:schema>');
        $subject = new ValidateXMLFinisher(new FileResourceLoader(new PathResolver($directory . '/base')));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(ValidateXMLFinisher::ERROR_MISSING_SCHEMA);

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'main.xsd']], $subject);
    }

    public function testDoesNotLoadExternalEntitiesOrDtds(): void
    {
        $this->loader->set('export.xml', '<?xml version="1.0"?><!DOCTYPE items SYSTEM "spy://items.dtd" [<!ENTITY e SYSTEM "spy://entity">]>'
            . '<items><item>&e;</item></items>');
        SpyStreamWrapper::register();
        try {
            $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']]);

            self::assertSame([], SpyStreamWrapper::$opened);
        } finally {
            SpyStreamWrapper::unregister();
        }
    }

    public function testDoesNotValidateAgainstTheDtdOfTheFile(): void
    {
        $this->loader->set('export.xml', '<?xml version="1.0"?><!DOCTYPE a [<!ELEMENT a (b)><!ELEMENT b EMPTY>]><a><c/></a>');

        $subject = $this->finish(['file' => 'export.xml']);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_VALID));
    }

    public function testThrowsExceptionIfSchemaDoesNotExist(): void
    {
        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(ValidateXMLFinisher::ERROR_MISSING_SCHEMA);

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'missing.xsd']]);
    }

    public function testThrowsExceptionIfFileTypeIsNotAllowed(): void
    {
        $this->loader->set('export.txt', self::VALID);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(FileTypePolicy::ERROR_NOT_ALLOWED);

        $this->finish(['file' => 'export.txt']);
    }

    public function testThrowsExceptionIfSchemaFileTypeIsNotAllowed(): void
    {
        $this->loader->set('schema.xml', self::SCHEMA);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(FileTypePolicy::ERROR_NOT_ALLOWED);

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xml']]);
    }

    public function testAcceptsOtherFileTypesFromTheApplication(): void
    {
        $this->loader->set('export.txt', self::VALID);
        $this->loader->set('schema.txt', self::SCHEMA);
        $subject = new ValidateXMLFinisher($this->loader, new FileTypePolicy(['txt']), new FileTypePolicy(['txt']));

        $this->finish(['file' => 'export.txt', 'schema' => ['file' => 'schema.txt']], $subject);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_VALID));
    }

    public function testValidatesLocalFileWithDefaultLoader(): void
    {
        $directory = $this->createTemporaryDirectory();
        file_put_contents($directory . '/export.xml', '<items><other/></items>');
        file_put_contents($directory . '/schema.xsd', self::SCHEMA);
        $subject = new ValidateXMLFinisher(new FileResourceLoader(new PathResolver($directory)));

        $this->finish(['file' => 'export.xml', 'schema' => ['file' => 'schema.xsd']], $subject);

        self::assertTrue($subject->hasMessageWithId(ValidateXMLFinisher::NOTICE_INVALID));
        self::assertFileExists($directory . '/export.xml');
    }

    public function testRestoresLibxmlErrorHandling(): void
    {
        $this->loader->set('export.xml', '<a>');
        $previous = libxml_use_internal_errors(false);
        try {
            $this->finish(['file' => 'export.xml']);

            self::assertFalse(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
