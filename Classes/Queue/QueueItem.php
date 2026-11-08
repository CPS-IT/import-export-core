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

namespace CPSIT\ImportExportCore\Queue;

/**
 * A record waiting in a queue. Items are created by a queue repository.
 */
final readonly class QueueItem
{
    /**
     * @param string $id assigned by the repository
     * @param string $queue identifier of the queue
     * @param array<mixed> $data the record
     */
    public function __construct(
        public string $id,
        public string $queue,
        public string $checksum,
        public array $data,
        public QueueItemStatus $status,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public function withStatus(QueueItemStatus $status, \DateTimeImmutable $updatedAt): self
    {
        return new self($this->id, $this->queue, $this->checksum, $this->data, $status, $this->createdAt, $updatedAt);
    }

    /**
     * Identifies equal records in a queue.
     *
     * @param array<mixed> $data
     * @throws \JsonException if the data cannot be encoded
     */
    public static function checksum(string $queue, array $data): string
    {
        return sha1($queue . "\n" . self::encode($data));
    }

    /**
     * Encoding used for the checksum and by repositories storing the data as
     * JSON.
     *
     * @param array<mixed> $data
     * @throws \JsonException
     */
    public static function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
