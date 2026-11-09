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
     * @param string $checksum of the source record when it was written, '' if unknown
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
     * Checksum of a source record, as stored by the processor.
     *
     * @param array<mixed> $record
     * @throws \JsonException if the record cannot be encoded
     */
    public static function checksum(array $record): string
    {
        return sha1(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
