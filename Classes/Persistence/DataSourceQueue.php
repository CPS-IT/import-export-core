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

namespace CPSIT\ImportExportCore\Persistence;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Queue\QueueItem;
use CPSIT\ImportExportCore\Queue\QueueRepositoryInterface;

/**
 * Reads up to `limit` new records from a queue and claims them. After the
 * task, the claimed items are marked as finished.
 *
 * Configuration:
 *
 * * `identifier` (string, required): the queue
 * * `limit` (int, default 10): items per run
 */
class DataSourceQueue implements DataSourceInterface, ConfigurableInterface, CompletableSourceInterface
{
    use ConfigurableTrait;

    public const string KEY_IDENTIFIER = 'identifier';
    public const string KEY_LIMIT = 'limit';
    public const int DEFAULT_LIMIT = 10;

    /**
     * @var list<QueueItem>
     */
    protected array $claimed = [];

    public function __construct(protected readonly QueueRepositoryInterface $repository)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!isset($configuration[self::KEY_IDENTIFIER])
            || !\is_string($configuration[self::KEY_IDENTIFIER])
            || $configuration[self::KEY_IDENTIFIER] === ''
        ) {
            return false;
        }

        return !isset($configuration[self::KEY_LIMIT])
            || (\is_int($configuration[self::KEY_LIMIT]) && $configuration[self::KEY_LIMIT] > 0);
    }

    /**
     * @return \Generator<array<mixed>>
     */
    public function getRecords(array $configuration): iterable
    {
        $queue = $configuration[self::KEY_IDENTIFIER];
        $limit = $configuration[self::KEY_LIMIT] ?? self::DEFAULT_LIMIT;
        if (!\is_string($queue) || !\is_int($limit)) {
            return;
        }

        foreach ($this->repository->claim($queue, $limit) as $item) {
            $this->claimed[] = $item;
            yield $item->data;
        }
    }

    public function complete(ResultInterface $result): void
    {
        foreach ($this->claimed as $item) {
            $this->repository->complete($item);
        }
        $this->claimed = [];
    }
}
