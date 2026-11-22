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

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;

/**
 * Loads an XML schema (XSD) named by the option `schema`: `{file: ...}` or
 * `{url: ...}`, passed to the resource loader.
 *
 * @internal
 */
final readonly class XmlSchema
{
    /**
     * @var list<string>
     */
    public const array DEFAULT_FILE_EXTENSIONS = ['xsd'];

    private ResourceLoaderInterface $resourceLoader;

    private FileTypePolicy $fileTypes;

    public function __construct(ResourceLoaderInterface $resourceLoader, ?FileTypePolicy $fileTypes = null)
    {
        $this->resourceLoader = $resourceLoader;
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
     * Returns the content of the schema.
     *
     * @param array<string, mixed> $schema value of the option `schema`
     * @param int $errorCode code of the exception if the schema is missing or empty
     *
     * @throws InvalidConfigurationException if the file type is not allowed
     * @throws MissingResourceException if the schema cannot be loaded or is empty
     */
    public function load(array $schema, int $errorCode): string
    {
        $location = ResourceLocation::fromConfiguration($schema);
        if (ResourceLocation::isFile($schema)) {
            $this->fileTypes->assertAllowed($location);
        }
        try {
            $stream = $this->resourceLoader->open($location);
            try {
                $content = (string)$stream;
            } finally {
                $stream->close();
            }
        } catch (MissingResourceException $exception) {
            throw new MissingResourceException(
                \sprintf('The schema "%s" cannot be loaded: %s', $location, $exception->getMessage()),
                $errorCode,
                $exception
            );
        }
        if (trim($content) === '') {
            throw new MissingResourceException(\sprintf('The schema "%s" is empty.', $location), $errorCode);
        }

        return $content;
    }
}
