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

use CPSIT\ImportExportCore\Exception\InvalidArgumentException;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;

/**
 * Loads an XML schema (XSD) named by the option `schema`: `{file: ...}` or
 * `{url: ...}`, passed to the resource loader.
 *
 * Schemas named in `xs:include`, `xs:import`, `xs:redefine` and
 * `xs:override` are loaded with the same resource loader and file type
 * policy, relative to the location of the schema that names them. libxml
 * does not load any file or URL itself: while `run()` executes, its external
 * entity loader serves only the loaded schemas and denies everything else,
 * e.g. external entities and DTDs of the validated documents.
 *
 * @internal
 */
final class XmlSchema
{
    /**
     * @var list<string>
     */
    public const array DEFAULT_FILE_EXTENSIONS = ['xsd'];

    /**
     * Maximum number of schema documents, including the main schema
     */
    public const int MAX_DOCUMENTS = 100;

    private const string NAMESPACE_XSD = 'http://www.w3.org/2001/XMLSchema';
    private const string URI_PREFIX = 'import-export-core-schema://document/';
    private const int LIBXML_OPTIONS = LIBXML_NONET;

    /**
     * Error code of libxml if the root element of the validated document is
     * not declared (XML_SCHEMAV_CVC_ELT_1)
     */
    private const int LIBXML_UNDECLARED_ROOT = 1845;
    private const string PROBE = '<probe xmlns="urn:cpsit:import-export-core:schema-probe"/>';

    private readonly FileTypePolicy $fileTypes;

    /**
     * @var array<string, string> content of the loaded schemas by their URI
     */
    private array $documents = [];

    public function __construct(
        private readonly ResourceLoaderInterface $resourceLoader,
        ?FileTypePolicy $fileTypes = null,
    ) {
        $this->fileTypes = $fileTypes ?? new FileTypePolicy(self::DEFAULT_FILE_EXTENSIONS);
    }

    /**
     * Whether the value of `schema` names exactly one file or URL
     */
    public static function isValid(mixed $schema): bool
    {
        return \is_array($schema) && ResourceLocation::isValid($schema);
    }

    /**
     * Loads the schema and the schemas it includes or imports and checks
     * that they form a valid XML schema.
     *
     * @param array<string, mixed> $schema value of the option `schema`
     * @param int $missingCode code of the exception if a schema is missing, empty or not allowed
     * @param int $invalidCode code of the exception if the schema is not a valid XML schema
     *
     * @throws InvalidConfigurationException if the file type of a schema is not allowed
     * @throws MissingResourceException if a schema cannot be loaded or is empty
     * @throws ParseException if the schema is not a valid XML schema
     * @return string URI of the main schema, to be passed to libxml within `run()`
     */
    public function load(array $schema, int $missingCode, int $invalidCode): string
    {
        $this->documents = [];
        /** @var array<string, string> $uris URI by location */
        $uris = [];
        $location = ResourceLocation::fromConfiguration($schema);
        $pending = [$location];
        $uris[$location] = self::uri(0);

        while ($pending !== []) {
            $current = array_shift($pending);
            $document = $this->parse($current, $missingCode, $invalidCode);
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('xs', self::NAMESPACE_XSD);
            $references = $xpath->query('/xs:schema/*[self::xs:include or self::xs:import or self::xs:redefine or self::xs:override]/@schemaLocation');
            foreach ($references ?: [] as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }
                $reference = self::resolve($current, trim($attribute->value));
                if (!isset($uris[$reference])) {
                    if (\count($uris) >= self::MAX_DOCUMENTS) {
                        throw new ParseException(
                            \sprintf('The schema "%s" includes more than %d schemas.', $location, self::MAX_DOCUMENTS),
                            $invalidCode
                        );
                    }
                    $uris[$reference] = self::uri(\count($uris));
                    $pending[] = $reference;
                }
                $attribute->value = $uris[$reference];
            }
            $this->documents[$uris[$current]] = (string)$document->saveXML();
        }

        $this->check($location, $invalidCode);

        return $uris[$location];
    }

    /**
     * Executes the callback while the external entity loader of libxml
     * serves only the loaded schemas.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        return self::withDocuments($this->documents, $callback);
    }

    /**
     * Executes the callback while the external entity loader of libxml
     * denies all files and URLs, e.g. external entities and DTDs.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function isolate(callable $callback): mixed
    {
        return self::withDocuments([], $callback);
    }

    /**
     * @template T
     * @param array<string, string> $documents
     * @param callable(): T $callback
     * @return T
     */
    private static function withDocuments(array $documents, callable $callback): mixed
    {
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(
            static function (?string $publicId, string $systemId) use ($documents) {
                if (!isset($documents[$systemId])) {
                    return null;
                }
                $stream = fopen('php://memory', 'w+b');
                if ($stream === false) {
                    return null;
                }
                fwrite($stream, $documents[$systemId]);
                rewind($stream);

                return $stream;
            }
        );
        try {
            return $callback();
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    private function parse(string $location, int $missingCode, int $invalidCode): \DOMDocument
    {
        if (!self::hasScheme($location)) {
            $this->fileTypes->assertAllowed($location);
        }
        try {
            $stream = $this->resourceLoader->open($location);
            try {
                $content = (string)$stream;
            } finally {
                $stream->close();
            }
        } catch (MissingResourceException|InvalidArgumentException $exception) {
            throw new MissingResourceException(
                \sprintf('The schema "%s" cannot be loaded: %s', $location, $exception->getMessage()),
                $missingCode,
                $exception
            );
        }
        if (trim($content) === '') {
            throw new MissingResourceException(\sprintf('The schema "%s" is empty.', $location), $missingCode);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new \DOMDocument();
            $loaded = self::isolate(static fn (): bool => $document->loadXML($content, self::LIBXML_OPTIONS));
            $error = self::firstError(libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded || $error !== null) {
            throw new ParseException(
                \sprintf('The schema "%s" is not valid XML%s.', $location, $error === null ? '' : ': ' . trim($error->message)),
                $invalidCode
            );
        }

        return $document;
    }

    /**
     * Compiles the schema by validating a document whose root element no
     * schema declares: only that error is expected.
     */
    private function check(string $location, int $invalidCode): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $probe = new \DOMDocument();
            $probe->loadXML(self::PROBE);
            $this->run(static fn (): bool => @$probe->schemaValidate(self::uri(0)));
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $error = self::firstError($errors, self::LIBXML_UNDECLARED_ROOT);
        if ($error !== null) {
            throw new ParseException(
                \sprintf('"%s" is not a valid XML schema: %s', $location, trim($error->message)),
                $invalidCode
            );
        }
    }

    /**
     * @param array<\LibXMLError> $errors
     */
    private static function firstError(array $errors, ?int $ignoredCode = null): ?\LibXMLError
    {
        foreach ($errors as $error) {
            if ($error->level !== LIBXML_ERR_WARNING && $error->code !== $ignoredCode) {
                return $error;
            }
        }

        return null;
    }

    private static function uri(int $number): string
    {
        return self::URI_PREFIX . $number . '.xsd';
    }

    /**
     * Resolves a schema location relative to the location of the schema
     * naming it. Absolute paths are kept, so that the resource loader
     * decides on them (`PathResolver` rejects them).
     */
    private static function resolve(string $base, string $location): string
    {
        if ($location === '' || self::hasScheme($location)) {
            return $location;
        }
        if (self::hasScheme($base)) {
            $parts = parse_url($base);
            if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
                return $location;
            }
            $origin = $parts['scheme'] . '://'
                . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
                . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $path = str_starts_with($location, '/') ? $location : self::directory($parts['path'] ?? '/') . $location;

            return $origin . '/' . self::normalize($path);
        }
        if (str_starts_with($location, '/') || str_starts_with($location, '\\') || preg_match('/^[A-Za-z]:/', $location) === 1) {
            return $location;
        }

        return self::normalize(self::directory($base) . $location);
    }

    /**
     * The path up to and including its last slash
     */
    private static function directory(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position + 1);
    }

    /**
     * Removes `.` and `..` segments. `..` segments that would leave the
     * root are kept, so that the resource loader rejects them.
     */
    private static function normalize(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private static function hasScheme(string $location): bool
    {
        return preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $location) === 1;
    }
}
