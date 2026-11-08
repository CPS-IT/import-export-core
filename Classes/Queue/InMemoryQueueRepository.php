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
 * Keeps the queue in memory, e.g. for tests or transfers within one process.
 */
final class InMemoryQueueRepository implements QueueRepositoryInterface
{
    /**
     * @var array<string, array<string, QueueItem>> queue => id => item
     */
    private array $items = [];

    private int $lastId = 0;

    public function enqueue(string $queue, array $data, bool $requeue = false): bool
    {
        $checksum = QueueItem::checksum($queue, $data);
        foreach ($this->items[$queue] ?? [] as $item) {
            if ($item->checksum !== $checksum) {
                continue;
            }
            if (!$requeue) {
                return false;
            }
            $this->store($item->withStatus(QueueItemStatus::New, new \DateTimeImmutable()));

            return true;
        }

        $now = new \DateTimeImmutable();
        $this->store(new QueueItem((string)++$this->lastId, $queue, $checksum, $data, QueueItemStatus::New, $now, $now));

        return true;
    }

    public function claim(string $queue, int $limit): array
    {
        $claimed = [];
        foreach ($this->items[$queue] ?? [] as $item) {
            if (\count($claimed) >= $limit) {
                break;
            }
            if ($item->status !== QueueItemStatus::New) {
                continue;
            }
            $claimed[] = $this->store($item->withStatus(QueueItemStatus::Processing, new \DateTimeImmutable()));
        }

        return $claimed;
    }

    public function complete(QueueItem $item): void
    {
        $this->setStatus($item, QueueItemStatus::Finished);
    }

    public function fail(QueueItem $item): void
    {
        $this->setStatus($item, QueueItemStatus::Failed);
    }

    public function release(string $queue): int
    {
        $released = 0;
        foreach ($this->items[$queue] ?? [] as $item) {
            if ($item->status === QueueItemStatus::Processing) {
                $this->store($item->withStatus(QueueItemStatus::New, new \DateTimeImmutable()));
                ++$released;
            }
        }

        return $released;
    }

    public function count(string $queue, QueueItemStatus $status): int
    {
        $count = 0;
        foreach ($this->items[$queue] ?? [] as $item) {
            if ($item->status === $status) {
                ++$count;
            }
        }

        return $count;
    }

    private function setStatus(QueueItem $item, QueueItemStatus $status): void
    {
        if (!isset($this->items[$item->queue][$item->id])) {
            throw new \InvalidArgumentException(
                \sprintf('Queue item "%s" does not exist in queue "%s".', $item->id, $item->queue),
                1794400101
            );
        }
        $this->store($this->items[$item->queue][$item->id]->withStatus($status, new \DateTimeImmutable()));
    }

    private function store(QueueItem $item): QueueItem
    {
        $this->items[$item->queue][$item->id] = $item;

        return $item;
    }
}
