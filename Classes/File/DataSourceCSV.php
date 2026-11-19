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
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\ResumableSourceInterface;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\ResourceLoaderInterface;
use CPSIT\ImportExportCore\Stream\ResourceStream;

/**
 * Reads records from a CSV file, one record per row.
 *
 * The file is read row by row, so large files are not held in memory. Empty
 * rows are skipped. A UTF-8 byte order mark at the start of the file is
 * removed. A row with more or fewer values than fields is skipped and
 * reported as error 1795200501.
 *
 * The source is resumable: the cursor holds the number and the position of
 * the last record read, so an interrupted task continues after it.
 *
 * Configuration:
 *
 * - `file` (string, required unless `url`): location of the file, passed to
 *   the resource loader; with the default loader a path relative to the
 *   working directory
 * - `url` (string, required unless `file`): URL of the file; requires a
 *   resource loader that supports URLs, e.g. `HttpResourceLoader`
 * - `fields` (list of strings or comma-separated string): field names, in
 *   the order of the columns. Without `fields`, the first row holds the
 *   field names.
 * - `header` (bool, default true without `fields`, false with `fields`):
 *   whether the first row holds field names. With `fields` and `header:
 *   true`, the first row is skipped.
 * - `delimiter` (string, default ','): one character
 * - `enclosure` (string, default '"'): one character
 * - `escape` (string, default '\'): one character, or '' for none
 * - `encoding` (string, default 'UTF-8'): encoding of the file; values are
 *   converted to UTF-8. Other encodings than UTF-8 require the PHP extension
 *   mbstring and must keep delimiter, enclosure and line breaks as single
 *   bytes, e.g. ISO-8859-1 or Windows-1252 (not UTF-16).
 * - `recordNumberField` (string): adds the number of the record (1 for the
 *   first row after the header) as this field, e.g. as source ID for the ID
 *   map
 */
class DataSourceCSV implements DataSourceInterface, ConfigurableInterface, ResumableSourceInterface, LoggingInterface
{
    use ConfigurableTrait;
    use LoggingTrait;

    public const string KEY_HEADER = 'header';
    public const string KEY_ENCODING = 'encoding';
    public const string KEY_RECORD_NUMBER_FIELD = 'recordNumberField';
    public const string DEFAULT_ENCODING = 'UTF-8';
    public const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";
    public const int ERROR_INVALID_ROW = 1795200501;
    public const int ERROR_INVALID_CURSOR = 1795200502;
    public const int ERROR_INVALID_CONFIGURATION = 1795200503;

    public const array ERROR_CODES = [
        self::ERROR_INVALID_ROW => [
            'Invalid row',
            'Row %d of "%s" has %d values, but there are %d fields. The row is skipped.',
        ],
    ];

    protected readonly ResourceLoaderInterface $resourceLoader;

    protected string $cursor = '';

    protected string $resumeCursor = '';

    public function __construct(?ResourceLoaderInterface $resourceLoader = null)
    {
        $this->resourceLoader = $resourceLoader ?? new FileResourceLoader();
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!ResourceLocation::isValid($configuration) || !CsvFormat::isValid($configuration)) {
            return false;
        }
        if (isset($configuration[self::KEY_HEADER]) && !\is_bool($configuration[self::KEY_HEADER])) {
            return false;
        }
        if (isset($configuration[self::KEY_RECORD_NUMBER_FIELD])
            && (!\is_string($configuration[self::KEY_RECORD_NUMBER_FIELD]) || $configuration[self::KEY_RECORD_NUMBER_FIELD] === '')
        ) {
            return false;
        }

        return !isset($configuration[self::KEY_ENCODING]) || self::isSupportedEncoding($configuration[self::KEY_ENCODING]);
    }

    /**
     * @throws MissingResourceException
     * @return \Generator<array<string, mixed>>
     */
    public function getRecords(array $configuration): iterable
    {
        if (!$this->isConfigurationValid($configuration)) {
            throw new \UnexpectedValueException('Invalid configuration for the CSV data source.', self::ERROR_INVALID_CONFIGURATION);
        }
        $location = ResourceLocation::fromConfiguration($configuration);
        $format = CsvFormat::fromConfiguration($configuration);
        $fields = CsvFormat::fields($configuration);
        $header = $configuration[self::KEY_HEADER] ?? $fields === null;
        $encoding = $configuration[self::KEY_ENCODING] ?? self::DEFAULT_ENCODING;
        $encoding = \is_string($encoding) && !self::isUtf8($encoding) ? $encoding : null;
        $recordNumberField = $configuration[self::KEY_RECORD_NUMBER_FIELD] ?? null;
        $recordNumberField = \is_string($recordNumberField) ? $recordNumberField : null;
        [$resumeRecord, $resumeRow, $resumeOffset] = $this->parseCursor($this->resumeCursor);
        $this->cursor = $this->resumeCursor;

        $handle = ResourceStream::toResource($this->resourceLoader->open($location), true);
        try {
            if (fread($handle, 3) !== self::BYTE_ORDER_MARK) {
                rewind($handle);
            }
            $rowNumber = 0;
            if ($header) {
                $row = $this->readRow($handle, $format, $encoding, $rowNumber);
                if ($row === null) {
                    return;
                }
                $fields ??= array_map(static fn (?string $value): string => (string)$value, $row);
            }

            $recordNumber = 0;
            if ($resumeRecord > 0) {
                if (fseek($handle, $resumeOffset) !== 0) {
                    throw new \UnexpectedValueException(
                        \sprintf('Cannot resume reading "%s" at cursor "%s".', $location, $this->resumeCursor),
                        self::ERROR_INVALID_CURSOR
                    );
                }
                $recordNumber = $resumeRecord;
                $rowNumber = $resumeRow;
            }

            while (($row = $this->readRow($handle, $format, $encoding, $rowNumber)) !== null) {
                ++$recordNumber;
                $this->cursor = $recordNumber . ':' . $rowNumber . ':' . (int)ftell($handle);
                $fields ??= array_map(strval(...), array_keys($row));
                if (\count($row) !== \count($fields)) {
                    $this->logError(self::ERROR_INVALID_ROW, [$rowNumber, $location, \count($row), \count($fields)]);
                    continue;
                }
                $record = array_combine($fields, $row);
                if ($recordNumberField !== null) {
                    $record[$recordNumberField] = $recordNumber;
                }

                yield $record;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Returns `<record number>:<row number>:<byte offset>` after the last
     * record read, or ''.
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
     * Reads the next row that is not empty. `$rowNumber` counts rows of the
     * file including empty ones; a value with line breaks is one row.
     *
     * @param resource $handle
     * @return list<string|null>|null null at the end of the file
     */
    protected function readRow($handle, CsvFormat $format, ?string $encoding, int &$rowNumber): ?array
    {
        while (($row = fgetcsv($handle, null, $format->delimiter, $format->enclosure, $format->escape)) !== false) {
            ++$rowNumber;
            if ($row === [null]) {
                continue;
            }
            if ($encoding !== null) {
                $row = array_map(
                    static fn (?string $value): ?string => $value === null ? null : (string)mb_convert_encoding($value, 'UTF-8', $encoding),
                    $row
                );
            }

            return $row;
        }

        return null;
    }

    /**
     * @return array{int, int, int} record number, row number and byte offset
     */
    protected function parseCursor(string $cursor): array
    {
        if ($cursor === '') {
            return [0, 0, 0];
        }
        if (preg_match('/^(\d+):(\d+):(\d+)$/', $cursor, $matches) !== 1) {
            throw new \UnexpectedValueException(
                \sprintf('Invalid cursor "%s" for the CSV data source.', $cursor),
                self::ERROR_INVALID_CURSOR
            );
        }

        return [(int)$matches[1], (int)$matches[2], (int)$matches[3]];
    }

    private static function isSupportedEncoding(mixed $encoding): bool
    {
        if (!\is_string($encoding) || $encoding === '') {
            return false;
        }
        if (self::isUtf8($encoding)) {
            return true;
        }
        if (!\function_exists('mb_list_encodings')) {
            return false;
        }

        return \in_array(strtolower($encoding), array_map(strtolower(...), mb_list_encodings()), true);
    }

    private static function isUtf8(string $encoding): bool
    {
        return \in_array(strtolower($encoding), ['utf-8', 'utf8'], true);
    }
}
