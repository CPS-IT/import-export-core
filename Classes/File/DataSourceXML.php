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
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\ResumableSourceInterface;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;
use CPSIT\ImportExportCore\Stream\ResourceStream;

/**
 * Reads records from an XML file, one record per element.
 *
 * With `element`, the file is read element by element (`XMLReader`), so large
 * files are not held in memory. With `expression`, the whole document is
 * loaded and the records are selected with XPath.
 *
 * Each record element is converted into an array: attributes in
 * `@attributes`, child elements as fields named by their local name (lists
 * for repeated elements, strings for elements with only text), and text next
 * to attributes or child elements in `@value`.
 *
 * The source is resumable: the cursor is the number of the last record read.
 * A resumed task reads the file from the beginning again and skips the
 * records up to the cursor.
 *
 * Configuration:
 *
 * - `file` (string, required unless `url`): location of the file, passed to
 *   the resource loader; with the default loader a path relative to the
 *   working directory
 * - `url` (string, required unless `file`): URL of the file; requires a
 *   resource loader that supports URLs, e.g. `HttpResourceLoader`
 * - `element` (string, required unless `expression`): name of the record
 *   elements, e.g. `item`, or `prefix:item` with a prefix of `namespaces`.
 *   Without prefix, elements of any namespace match. Record elements must
 *   not be nested in each other.
 * - `expression` (string, required unless `element`): XPath expression
 *   selecting the record elements, e.g. `//channel/item`; other nodes it
 *   selects are skipped
 * - `namespaces` (map of prefix => URI): namespaces for `element` and
 *   `expression`
 * - `recordNumberField` (string): adds the number of the record (1 for the
 *   first) as this field, e.g. as source ID for the ID map
 */
class DataSourceXML implements DataSourceInterface, ConfigurableInterface, ResumableSourceInterface
{
    use ConfigurableTrait;

    public const string KEY_ELEMENT = 'element';
    public const string KEY_EXPRESSION = 'expression';
    public const string KEY_NAMESPACES = 'namespaces';
    public const string KEY_RECORD_NUMBER_FIELD = 'recordNumberField';
    public const int ERROR_INVALID_CONFIGURATION = 1795200601;
    public const int ERROR_INVALID_XML = 1795200602;
    public const int ERROR_INVALID_CURSOR = 1795200603;
    private const int LIBXML_OPTIONS = LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE;

    protected readonly ResourceLoaderInterface $resourceLoader;

    protected readonly XmlElementConverter $converter;

    protected string $cursor = '';

    protected string $resumeCursor = '';

    public function __construct(?ResourceLoaderInterface $resourceLoader = null)
    {
        $this->resourceLoader = $resourceLoader ?? new FileResourceLoader();
        $this->converter = new XmlElementConverter();
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!ResourceLocation::isValid($configuration)) {
            return false;
        }
        $element = $configuration[self::KEY_ELEMENT] ?? null;
        $expression = $configuration[self::KEY_EXPRESSION] ?? null;
        if (($element === null) === ($expression === null)) {
            return false;
        }
        $namespaces = $configuration[self::KEY_NAMESPACES] ?? [];
        if (!\is_array($namespaces)) {
            return false;
        }
        foreach ($namespaces as $prefix => $uri) {
            if (!\is_string($prefix) || !self::isName($prefix) || !\is_string($uri) || $uri === '') {
                return false;
            }
        }
        if (isset($configuration[self::KEY_RECORD_NUMBER_FIELD])
            && (!\is_string($configuration[self::KEY_RECORD_NUMBER_FIELD]) || $configuration[self::KEY_RECORD_NUMBER_FIELD] === '')
        ) {
            return false;
        }
        if ($element !== null) {
            if (!\is_string($element)) {
                return false;
            }
            $parts = explode(':', $element);

            return match (\count($parts)) {
                1 => self::isName($element),
                2 => self::isName($parts[1]) && \array_key_exists($parts[0], $namespaces),
                default => false,
            };
        }

        return \is_string($expression) && trim($expression) !== '';
    }

    /**
     * @throws MissingResourceException
     * @throws ParseException if the XML or the XPath expression is invalid
     * @return \Generator<array<string, mixed>>
     */
    public function getRecords(array $configuration): iterable
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new \UnexpectedValueException('Invalid configuration for the XML data source.', self::ERROR_INVALID_CONFIGURATION);
        }
        $location = ResourceLocation::fromConfiguration($configuration);
        /** @var array<string, string> $namespaces */
        $namespaces = $configuration[self::KEY_NAMESPACES] ?? [];
        $recordNumberField = $configuration[self::KEY_RECORD_NUMBER_FIELD] ?? null;
        $recordNumberField = \is_string($recordNumberField) ? $recordNumberField : null;
        $skip = $this->parseCursor($this->resumeCursor);
        $this->cursor = $this->resumeCursor;

        $element = $configuration[self::KEY_ELEMENT] ?? null;
        $elements = \is_string($element)
            ? $this->readElements($location, $element, $namespaces)
            : $this->queryElements($location, (string)$configuration[self::KEY_EXPRESSION], $namespaces);

        $recordNumber = 0;
        foreach ($elements as $node) {
            ++$recordNumber;
            if ($recordNumber <= $skip) {
                continue;
            }
            $this->cursor = (string)$recordNumber;
            $record = $this->converter->toRecord($node);
            if ($recordNumberField !== null) {
                $record[$recordNumberField] = $recordNumber;
            }

            yield $record;
        }
    }

    /**
     * Returns the number of the last record read, or ''.
     */
    public function getCursor(): string
    {
        return $this->cursor;
    }

    public function resumeFrom(string $cursor): void
    {
        $this->parseCursor($cursor);
        $this->resumeCursor = $cursor;
    }

    /**
     * @param array<string, string> $namespaces
     * @return \Generator<\DOMElement>
     */
    protected function readElements(string $location, string $element, array $namespaces): \Generator
    {
        $parts = explode(':', $element, 2);
        $localName = $parts[1] ?? $parts[0];
        $namespace = isset($parts[1]) ? $namespaces[$parts[0]] : null;

        $handle = ResourceStream::toResource($this->resourceLoader->open($location));
        $temporaryFile = null;
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $metadata = stream_get_meta_data($handle);
            if ($metadata['wrapper_type'] === 'plainfile' && isset($metadata['uri']) && is_file($metadata['uri'])) {
                $path = $metadata['uri'];
            } else {
                $path = $temporaryFile = $this->copyToTemporaryFile($handle);
            }
            if (!@$reader->open($path, null, self::LIBXML_OPTIONS)) {
                throw $this->parseError($location);
            }
            $document = new \DOMDocument();

            $read = @$reader->read();
            while ($read) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === $localName
                    && ($namespace === null || $reader->namespaceURI === $namespace)
                ) {
                    $node = $reader->expand($document);
                    if (!$node instanceof \DOMElement) {
                        throw $this->parseError($location);
                    }
                    yield $node;
                    $read = @$reader->next();
                    continue;
                }
                $read = @$reader->read();
            }
            if (libxml_get_errors() !== []) {
                throw $this->parseError($location);
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (\is_resource($handle)) {
                fclose($handle);
            }
            if ($temporaryFile !== null) {
                @unlink($temporaryFile);
            }
        }
    }

    /**
     * @param array<string, string> $namespaces
     * @return list<\DOMElement>
     */
    protected function queryElements(string $location, string $expression, array $namespaces): array
    {
        $stream = $this->resourceLoader->open($location);
        try {
            $content = (string)$stream;
        } finally {
            $stream->close();
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new \DOMDocument();
            if (trim($content) === '' || !$document->loadXML($content, self::LIBXML_OPTIONS)) {
                throw $this->parseError($location);
            }
            $xpath = new \DOMXPath($document);
            foreach ($namespaces as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }
            $nodes = @$xpath->query($expression);
            if ($nodes === false) {
                throw new ParseException(
                    \sprintf('Invalid XPath expression "%s".', $expression),
                    self::ERROR_INVALID_XML
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    protected function parseCursor(string $cursor): int
    {
        if ($cursor === '') {
            return 0;
        }
        if (preg_match('/^\d+$/', $cursor) !== 1) {
            throw new \UnexpectedValueException(
                \sprintf('Invalid cursor "%s" for the XML data source.', $cursor),
                self::ERROR_INVALID_CURSOR
            );
        }

        return (int)$cursor;
    }

    /**
     * @param resource $handle
     */
    private function copyToTemporaryFile($handle): string
    {
        $file = tempnam(sys_get_temp_dir(), 'import-export-xml');
        $target = $file === false ? false : fopen($file, 'wb');
        if ($file === false || $target === false) {
            throw new \RuntimeException('Unable to create a temporary file', 1795200604);
        }
        stream_copy_to_stream($handle, $target);
        fclose($target);

        return $file;
    }

    private function parseError(string $location): ParseException
    {
        $error = libxml_get_last_error();
        $detail = $error === false ? '' : \sprintf(': %s in line %d', trim($error->message), $error->line);

        return new ParseException(
            \sprintf('"%s" is not valid XML%s.', $location, $detail),
            self::ERROR_INVALID_XML
        );
    }

    private static function isName(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $name) === 1;
    }
}
