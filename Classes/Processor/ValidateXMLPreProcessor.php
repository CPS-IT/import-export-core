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

namespace CPSIT\ImportExportCore\Processor;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\File\XmlSchema;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;

/**
 * Checks that fields of a record hold well-formed XML and, with `schema`,
 * that the XML is valid against an XML schema (XSD).
 *
 * A field is valid if it holds a string or a `DOMDocument` with XML. A
 * missing field, an empty string or a value of another type is invalid. The
 * fields are checked in order; at the first invalid field, the record gets
 * the field `xmlValidationFailed` with the value true and the remaining
 * fields are not checked. Each error of the XML parser or the schema
 * validation is logged as error 1646304431, with the field, the record ID
 * and the error in the additional information. No message is logged for a
 * missing or empty field. The record is passed on in any case; a filter or
 * a condition on `xmlValidationFailed` can leave it out.
 *
 * The schema is loaded once per task. `schema.file` must have one of the
 * allowed file types (`FileTypePolicy`), by default `xsd`; the application
 * can pass other types as constructor argument.
 *
 * Moved from PreProcessor\ValidateXML of t3import_export.
 *
 * Configuration:
 *
 * - `fields` (list or comma-separated string, required): fields to check
 * - `identifier` (string, default `uid`): field with the ID of the record,
 *   used in the messages
 * - `schema` (map): the XML schema, `{file: ...}` or `{url: ...}`, passed to
 *   the resource loader; with the default loader a path relative to the
 *   working directory. A schema that cannot be loaded or is empty throws a
 *   `MissingResourceException` (1646301113)
 */
final class ValidateXMLPreProcessor implements PreProcessorInterface, ConfigurableInterface, LoggingInterface
{
    use ConfigurableTrait;
    use LoggingTrait;

    public const string KEY_FIELDS = 'fields';
    public const string KEY_IDENTIFIER = 'identifier';
    public const string KEY_SCHEMA = 'schema';
    public const string KEY_VALIDATION_FAILED = 'xmlValidationFailed';
    public const string DEFAULT_IDENTIFIER_FIELD = 'uid';
    public const int ERROR_INVALID_XML = 1646304431;
    public const int ERROR_MISSING_SCHEMA = 1646301113;

    public const array ERROR_CODES = [
        self::ERROR_INVALID_XML => ['Validation Error', 'XML is invalid'],
    ];

    private const string ERROR_TEMPLATE = "Error validating content of field %s:\n"
        . "Record ID %s\n"
        . "Error Code (lib xml): %d\n"
        . "Level: %d\n"
        . "Message: %s\n"
        . "Line: %d\n"
        . 'Column: %d';

    private readonly XmlSchema $schemaLoader;

    private ?string $schema = null;

    /**
     * @param FileTypePolicy|null $fileTypes allowed types of `schema.file`; default: `XmlSchema::DEFAULT_FILE_EXTENSIONS`
     */
    public function __construct(?ResourceLoaderInterface $resourceLoader = null, ?FileTypePolicy $fileTypes = null)
    {
        $this->schemaLoader = new XmlSchema($resourceLoader ?? new FileResourceLoader(), $fileTypes);
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (FieldList::parse($configuration[self::KEY_FIELDS] ?? null) === null) {
            return false;
        }
        $identifier = $configuration[self::KEY_IDENTIFIER] ?? self::DEFAULT_IDENTIFIER_FIELD;
        if (!\is_string($identifier) || $identifier === '') {
            return false;
        }

        return !isset($configuration[self::KEY_SCHEMA]) || XmlSchema::isValid($configuration[self::KEY_SCHEMA]);
    }

    public function setConfiguration(array $configuration): void
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new InvalidConfigurationException('Configuration for ' . self::class . ' is not valid.', 1_451_659_793);
        }
        $this->configuration = $configuration;
        $this->schema = null;
    }

    /**
     * @throws InvalidConfigurationException if the file type of the schema is not allowed
     * @throws MissingResourceException if the schema cannot be loaded or is empty
     */
    public function process(array $record): array
    {
        $schema = $this->getSchema();
        /** @var string $identifierField checked by isConfigurationValid() */
        $identifierField = $this->configuration[self::KEY_IDENTIFIER] ?? self::DEFAULT_IDENTIFIER_FIELD;
        $identifier = $record[$identifierField] ?? '';
        $identifier = \is_scalar($identifier) ? (string)$identifier : '';

        foreach (FieldList::parse($this->configuration[self::KEY_FIELDS] ?? null) ?? [] as $field) {
            if (!$this->isValidXml($record[$field] ?? null, $field, $identifier, $schema)) {
                $record[self::KEY_VALIDATION_FAILED] = true;

                return $record;
            }
        }

        return $record;
    }

    private function isValidXml(mixed $value, string $field, string $identifier, string $schema): bool
    {
        $xml = match (true) {
            $value instanceof \DOMDocument => (string)$value->saveXML(),
            \is_string($value) => $value,
            default => '',
        };
        if (trim($xml) === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new \DOMDocument('1.0', 'utf-8');
            if ($document->loadXML($xml, LIBXML_NONET) && $schema !== '') {
                $document->schemaValidateSource($schema);
            }
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($errors as $error) {
            $this->logError(self::ERROR_INVALID_XML, null, [
                \sprintf(
                    self::ERROR_TEMPLATE,
                    $field,
                    $identifier,
                    $error->code,
                    $error->level,
                    trim($error->message),
                    $error->line,
                    $error->column
                ),
            ]);
        }

        return $errors === [];
    }

    private function getSchema(): string
    {
        if ($this->schema === null) {
            $schema = $this->configuration[self::KEY_SCHEMA] ?? null;
            /** @var array<string, mixed>|null $schema checked by isConfigurationValid() */
            $this->schema = $schema === null ? '' : $this->schemaLoader->load($schema, self::ERROR_MISSING_SCHEMA);
        }

        return $this->schema;
    }
}
