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

namespace CPSIT\ImportExportCore\File;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\TaskContext;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;
use CPSIT\ImportExportCore\Stream\ResourceStream;

/**
 * Checks after the task that an XML file, e.g. the file written by the
 * target, is well-formed and, with `schema`, valid against an XML schema
 * (XSD). The whole file is read, element by element (`XMLReader`), so large
 * files are not held in memory. A DTD in the file is not used for
 * validation, and libxml loads no external entities or DTDs.
 *
 * The result is logged as notice: 1508914030 if the file is valid,
 * 1508776068 with the number of errors and the errors in the additional
 * information if it is not. A file that is missing or empty is logged as
 * error 1508914547. The finisher does not stop the task or change the file.
 *
 * The schema is loaded and checked before the file is read. Schemas it
 * names in `xs:include`, `xs:import`, `xs:redefine` or `xs:override` are
 * loaded with the same resource loader, relative to the location of the
 * naming schema; with the default loader, absolute paths and URLs are not
 * allowed.
 *
 * `file` must have one of the allowed file types (`FileTypePolicy`), by
 * default `xml`, schema files by default `xsd`. The application can pass
 * other types as constructor arguments.
 *
 * Moved from Finisher\ValidateXML of t3import_export.
 *
 * Configuration:
 *
 * - `file` (string, required unless `url`): location of the XML file, passed
 *   to the resource loader; with the default loader a path relative to the
 *   working directory
 * - `url` (string, required unless `file`): URL of the XML file; requires a
 *   resource loader that supports URLs, e.g. `HttpResourceLoader`
 * - `schema` (map): the XML schema, `{file: ...}` or `{url: ...}`. A schema
 *   that cannot be loaded, is empty or includes a schema that cannot be
 *   loaded throws a `MissingResourceException` (1795200901), a schema that
 *   is not a valid XML schema a `ParseException` (1795200904)
 */
final class ValidateXMLFinisher implements FinisherInterface, ConfigurableInterface, LoggingInterface
{
    use ConfigurableTrait;
    use LoggingTrait;

    /**
     * @var list<string>
     */
    public const array DEFAULT_FILE_EXTENSIONS = ['xml'];

    public const string KEY_SCHEMA = 'schema';
    public const int NOTICE_INVALID = 1508776068;
    public const int NOTICE_VALID = 1508914030;
    public const int ERROR_EMPTY_RESOURCE = 1508914547;
    public const int ERROR_MISSING_SCHEMA = 1795200901;
    public const int ERROR_TEMPORARY_FILE = 1795200902;
    public const int ERROR_INVALID_SCHEMA = 1795200904;

    public const array NOTICE_CODES = [
        self::NOTICE_INVALID => ['Validation failed', 'XML is invalid. There %s %d %s.'],
        self::NOTICE_VALID => ['Validation succeeded', 'XML is valid.'],
    ];

    public const array ERROR_CODES = [
        self::ERROR_EMPTY_RESOURCE => ['Empty resource', 'Could not load resource or resource empty'],
    ];

    private const int LIBXML_OPTIONS = LIBXML_NONET | LIBXML_PARSEHUGE;

    private readonly ResourceLoaderInterface $resourceLoader;

    private readonly FileTypePolicy $fileTypes;

    private readonly XmlSchema $schemaLoader;

    /**
     * @param FileTypePolicy|null $fileTypes allowed types of `file`; default: `DEFAULT_FILE_EXTENSIONS`
     * @param FileTypePolicy|null $schemaFileTypes allowed types of `schema.file`; default: `XmlSchema::DEFAULT_FILE_EXTENSIONS`
     */
    public function __construct(
        ?ResourceLoaderInterface $resourceLoader = null,
        ?FileTypePolicy $fileTypes = null,
        ?FileTypePolicy $schemaFileTypes = null,
    ) {
        $this->resourceLoader = $resourceLoader ?? new FileResourceLoader();
        $this->fileTypes = $fileTypes ?? new FileTypePolicy(self::DEFAULT_FILE_EXTENSIONS);
        $this->schemaLoader = new XmlSchema($this->resourceLoader, $schemaFileTypes);
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!ResourceLocation::isValid($configuration)) {
            return false;
        }

        return !isset($configuration[self::KEY_SCHEMA]) || XmlSchema::isValid($configuration[self::KEY_SCHEMA]);
    }

    /**
     * @throws InvalidConfigurationException if the type of the file or a schema is not allowed
     * @throws MissingResourceException if a schema cannot be loaded or is empty
     * @throws ParseException if the schema is not a valid XML schema
     */
    public function finish(TaskContext $context): void
    {
        $location = ResourceLocation::fromConfiguration($this->configuration);
        if (ResourceLocation::isFile($this->configuration)) {
            $this->fileTypes->assertAllowed($location);
        }
        $schema = $this->configuration[self::KEY_SCHEMA] ?? null;
        /** @var array<string, mixed>|null $schema checked by isConfigurationValid() */
        $schemaUri = $schema === null
            ? null
            : $this->schemaLoader->load($schema, self::ERROR_MISSING_SCHEMA, self::ERROR_INVALID_SCHEMA);

        try {
            $handle = ResourceStream::toResource($this->resourceLoader->open($location));
        } catch (MissingResourceException) {
            $this->logError(self::ERROR_EMPTY_RESOURCE, null, [$location]);

            return;
        }

        $temporaryFile = null;
        try {
            $metadata = stream_get_meta_data($handle);
            if ($metadata['wrapper_type'] === 'plainfile' && isset($metadata['uri']) && is_file($metadata['uri'])) {
                $path = $metadata['uri'];
            } else {
                $path = $temporaryFile = self::copyToTemporaryFile($handle);
            }
            if ((int)filesize($path) === 0) {
                $this->logError(self::ERROR_EMPTY_RESOURCE, null, [$location]);

                return;
            }
            $errors = $this->schemaLoader->run(static fn (): array => self::validate($path, $schemaUri));
        } finally {
            if (\is_resource($handle)) {
                fclose($handle);
            }
            if ($temporaryFile !== null) {
                @unlink($temporaryFile);
            }
        }

        if ($errors === []) {
            $this->logNotice(self::NOTICE_VALID);

            return;
        }
        $count = \count($errors);
        $this->logNotice(
            self::NOTICE_INVALID,
            [$count > 1 ? 'were' : 'was', $count, $count > 1 ? 'errors' : 'error'],
            $errors
        );
    }

    /**
     * @return list<string> the errors, empty if the file is valid
     */
    private static function validate(string $path, ?string $schemaUri): array
    {
        $reader = new \XMLReader();
        $messages = [];
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            if (!@$reader->open($path, null, self::LIBXML_OPTIONS)) {
                $messages[] = 'The file could not be opened.';
            } elseif ($schemaUri !== null && !@$reader->setSchema($schemaUri)) {
                $messages[] = 'The schema could not be applied.';
            } else {
                while (@$reader->read()) {
                    // reading the document reports its errors to libxml
                }
            }
            $reader->close();
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($errors as $error) {
            $messages[] = \sprintf('Line %d, column %d: %s', $error->line, $error->column, trim($error->message));
        }

        return $messages;
    }

    /**
     * @param resource $handle
     */
    private static function copyToTemporaryFile($handle): string
    {
        $file = self::createTemporaryFile();
        $target = fopen($file, 'wb');
        if ($target === false) {
            throw new \RuntimeException('Unable to create a temporary file', self::ERROR_TEMPORARY_FILE);
        }
        stream_copy_to_stream($handle, $target);
        fclose($target);

        return $file;
    }

    private static function createTemporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'import-export-xml');
        if ($file === false) {
            throw new \RuntimeException('Unable to create a temporary file', self::ERROR_TEMPORARY_FILE);
        }

        return $file;
    }
}
