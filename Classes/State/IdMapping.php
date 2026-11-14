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

namespace CPSIT\ImportExportCore\State;

/**
 * Maps the ID of a record in the source to the ID of the record written to
 * the target.
 */
readonly class IdMapping
{
    /**
     * @param string $namespace e.g. a table or entity type
     * @param string $checksum of the record when it was written (see recordChecksum()), '' if unknown
     */
    public function __construct(
        public string $namespace,
        public string $sourceId,
        public string $targetId,
        public string $checksum = '',
    ) {
    }

    public function exists(): bool
    {
        return true;
    }

    /**
     * Checksum of a record as the processor stores it: of the record after the
     * pre-processors, without its ID field. The ID is the key of the mapping;
     * leaving it out keeps the checksum stable when a pre-processor sets the
     * target ID in that field (`TargetIdPreProcessor`), which it does only
     * once a mapping exists.
     *
     * @param array<mixed> $record
     * @throws \JsonException if the record cannot be encoded
     */
    public static function recordChecksum(array $record, string $idField): string
    {
        unset($record[$idField]);

        return self::checksum($record);
    }

    /**
     * Checksum of a record.
     *
     * @param array<mixed> $record
     * @throws \JsonException if the record cannot be encoded
     */
    public static function checksum(array $record): string
    {
        return sha1(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
