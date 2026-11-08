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
use CPSIT\ImportExportCore\Queue\QueueRepositoryInterface;

/**
 * Writes records into a queue. Records that are already in the queue (same
 * checksum) are skipped, or set back to new with `allowUpdate`.
 *
 * Configuration:
 *
 * * `identifier` (string, required): the queue
 * * `allowUpdate` (bool, default false)
 */
class DataTargetQueue implements DataTargetInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public const string KEY_IDENTIFIER = 'identifier';
    public const string KEY_ALLOW_UPDATE = 'allowUpdate';

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

        return !isset($configuration[self::KEY_ALLOW_UPDATE]) || \is_bool($configuration[self::KEY_ALLOW_UPDATE]);
    }

    /**
     * @return bool whether the record was added or set back to new
     */
    public function persist(array|object $object, ?array $configuration = null): bool
    {
        if (!\is_array($object)) {
            throw new \UnexpectedValueException(
                \sprintf('Only arrays can be written into a queue, got %s.', get_debug_type($object)),
                1794400111
            );
        }
        $configuration ??= $this->configuration;
        $queue = $configuration[self::KEY_IDENTIFIER] ?? '';
        if (!\is_string($queue) || $queue === '') {
            throw new \UnexpectedValueException('Missing queue identifier.', 1794400112);
        }

        return $this->repository->enqueue($queue, $object, ($configuration[self::KEY_ALLOW_UPDATE] ?? false) === true);
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): void
    {
    }
}
