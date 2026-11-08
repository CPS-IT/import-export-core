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

namespace CPSIT\ImportExportCore\Domain\Model;

use CPSIT\ImportExportCore\Messaging\MessageInterface;

/**
 * Result of processing one or more transfer tasks.
 *
 * Holds the processed records and the messages reported by components.
 * Additional information (e.g. about a written file) can be attached with
 * setInfo().
 *
 * @extends \Traversable<int, mixed>
 */
interface ResultInterface extends \Traversable, \Countable
{
    /**
     * Adds a processed record.
     */
    public function add(mixed $newElement): void;

    /**
     * Returns all processed records.
     *
     * @return array<mixed>
     */
    public function toArray(): array;

    /**
     * Adds messages. Existing messages are kept.
     *
     * @param array<MessageInterface> $messages
     */
    public function addMessages(array $messages): void;

    /**
     * Returns all messages. Messages are kept.
     *
     * @return array<MessageInterface>
     */
    public function getMessages(): array;

    /**
     * Tells by id if the result contains a certain message.
     * Not all messages have an id.
     */
    public function hasMessageWithId(int $id): bool;

    /**
     * Attaches additional information to the result.
     */
    public function setInfo(mixed $mixed): void;

    /**
     * Returns the additional information, if any.
     */
    public function getInfo(): mixed;

    /**
     * Counters of the transfer. They are kept even if the converted records
     * are not collected.
     */
    public function getStatistics(): TransferStatistics;
}
