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
use CPSIT\ImportExportCore\Exception\InvalidArgumentException;
use CPSIT\ImportExportCore\Exception\PersistenceException;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Resource\FileTypePolicy;
use CPSIT\ImportExportCore\Resource\PathResolver;
use CPSIT\ImportExportCore\Resource\PathResolverInterface;

/**
 * Writes records into a CSV file, one row per record.
 *
 * The file is created, or replaced, when the first record is written, and
 * closed in `persistAll()`. Records must be arrays. Values that are not
 * scalar are written as JSON; `Stringable` objects as string. Fields missing
 * in a record are written as empty values.
 *
 * `file` must have one of the allowed file types (`FileTypePolicy`), by
 * default `csv`, `tsv` or `txt`. The application can pass other types as
 * constructor argument. Executable files are never allowed.
 *
 * Configuration:
 *
 * - `file` (string, required): path of the file, resolved by the path
 *   resolver; by default relative to the working directory. Missing
 *   directories are created.
 * - `fields` (list of strings or comma-separated string): fields to write,
 *   in this order; without `fields`, the fields of the first record
 * - `header` (bool, default true): writes the field names in the first row
 * - `delimiter` (string, default ','): one character
 * - `enclosure` (string, default '"'): one character
 * - `escape` (string, default '\'): one character, or '' for none
 * - `bom` (bool, default false): starts the file with a UTF-8 byte order
 *   mark, e.g. for spreadsheet applications
 */
class DataTargetCSV implements DataTargetInterface, ConfigurableInterface
{
    /**
     * @var list<string>
     */
    public const array DEFAULT_FILE_EXTENSIONS = ['csv', 'tsv', 'txt'];

    use ConfigurableTrait;

    public const string KEY_FILE = 'file';
    public const string KEY_HEADER = 'header';
    public const string KEY_BOM = 'bom';
    public const int ERROR_INVALID_RECORD = 1795200701;
    public const int ERROR_WRITE_FAILED = 1795200702;

    protected readonly PathResolverInterface $pathResolver;

    protected readonly FileTypePolicy $fileTypes;

    /**
     * @var resource|null
     */
    protected $handle;

    /**
     * @var list<string>
     */
    protected array $fields = [];

    /**
     * @param FileTypePolicy|null $fileTypes allowed types of `file`; default: `DEFAULT_FILE_EXTENSIONS`
     */
    public function __construct(?PathResolverInterface $pathResolver = null, ?FileTypePolicy $fileTypes = null)
    {
        $this->pathResolver = $pathResolver ?? new PathResolver();
        $this->fileTypes = $fileTypes ?? new FileTypePolicy(self::DEFAULT_FILE_EXTENSIONS);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isConfigurationValid(array $configuration): bool
    {
        $file = $configuration[self::KEY_FILE] ?? null;
        if (!\is_string($file) || trim($file) === '' || !CsvFormat::isValid($configuration)) {
            return false;
        }
        foreach ([self::KEY_HEADER, self::KEY_BOM] as $key) {
            if (isset($configuration[$key]) && !\is_bool($configuration[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws PersistenceException if the file cannot be written
     */
    public function persist(array|object $object, ?array $configuration = null): bool
    {
        if (!\is_array($object)) {
            throw new \UnexpectedValueException(
                \sprintf('The CSV target writes arrays, got %s.', get_debug_type($object)),
                self::ERROR_INVALID_RECORD
            );
        }
        $configuration ??= $this->configuration;
        $format = CsvFormat::fromConfiguration($configuration);
        if ($this->handle === null) {
            $this->open($configuration, array_map(strval(...), array_keys($object)), $format);
        }

        $row = [];
        foreach ($this->fields as $field) {
            $row[] = self::toString($object[$field] ?? null);
        }
        $this->writeRow($row, $format);

        return true;
    }

    /**
     * Closes the file. If no record was written but `fields` are configured,
     * a file with only the header is written.
     */
    public function persistAll(mixed $result = null, ?array $configuration = null): void
    {
        $configuration ??= $this->configuration;
        if ($this->handle === null && CsvFormat::fields($configuration) !== null) {
            $this->open($configuration, [], CsvFormat::fromConfiguration($configuration));
        }
        $this->close();
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<string> $recordFields fields of the first record
     */
    protected function open(array $configuration, array $recordFields, CsvFormat $format): void
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new \UnexpectedValueException('Invalid configuration for the CSV data target.', 1795200703);
        }
        /** @var string $file */
        $file = $configuration[self::KEY_FILE];
        $this->fileTypes->assertAllowed($file);
        try {
            $path = $this->pathResolver->resolve($file);
        } catch (InvalidArgumentException $exception) {
            throw new PersistenceException($exception->getMessage(), self::ERROR_WRITE_FAILED, $exception);
        }
        $directory = \dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw $this->writeFailed($file);
        }
        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw $this->writeFailed($file);
        }
        $this->handle = $handle;
        $this->fields = CsvFormat::fields($configuration) ?? $recordFields;

        if (($configuration[self::KEY_BOM] ?? false) === true) {
            fwrite($handle, DataSourceCSV::BYTE_ORDER_MARK);
        }
        if (($configuration[self::KEY_HEADER] ?? true) === true) {
            $this->writeRow($this->fields, $format);
        }
    }

    /**
     * @param list<string> $row
     */
    protected function writeRow(array $row, CsvFormat $format): void
    {
        if ($this->handle === null
            || fputcsv($this->handle, $row, $format->delimiter, $format->enclosure, $format->escape, "\n") === false
        ) {
            throw $this->writeFailed((string)($this->configuration[self::KEY_FILE] ?? ''));
        }
    }

    protected function close(): void
    {
        if (\is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->fields = [];
    }

    private static function toString(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            \is_bool($value) => $value ? '1' : '0',
            \is_scalar($value), $value instanceof \Stringable => (string)$value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        };
    }

    private function writeFailed(string $file): PersistenceException
    {
        return new PersistenceException(\sprintf('Cannot write the file "%s".', $file), self::ERROR_WRITE_FAILED);
    }
}
