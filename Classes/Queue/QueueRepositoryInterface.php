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
 * Stores records for staged transfers: one task writes records into a queue
 * (`DataTargetQueue`), another one reads them in portions
 * (`DataSourceQueue`), e.g. once per scheduler run.
 *
 * Items are claimed when they are read (status processing) and marked
 * finished or failed afterwards. Items of an interrupted run stay in
 * processing until they are released.
 */
interface QueueRepositoryInterface
{
    /**
     * Adds a record unless an item with the same checksum exists in the queue.
     * With `$requeue`, an existing item is set back to new.
     *
     * @param array<mixed> $data
     * @return bool whether an item was added or set back to new
     */
    public function enqueue(string $queue, array $data, bool $requeue = false): bool;

    /**
     * Returns up to `$limit` new items in the order they were added and marks
     * them as processing.
     *
     * @return list<QueueItem> the claimed items, with status processing
     */
    public function claim(string $queue, int $limit): array;

    public function complete(QueueItem $item): void;

    public function fail(QueueItem $item): void;

    /**
     * Sets items in processing back to new, e.g. after an interrupted run.
     *
     * @return int number of released items
     */
    public function release(string $queue): int;

    public function count(string $queue, QueueItemStatus $status): int;
}
