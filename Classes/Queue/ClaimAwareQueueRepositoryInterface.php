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
 * Queue repositories that give each claim a token (`QueueItem::$claimToken`)
 * and can change items only while they are still claimed with it.
 *
 * `DataSourceQueue` uses it if the repository implements it, so that a failed
 * run does not mark items that were released and claimed again by another
 * run, or finished in the meantime.
 */
interface ClaimAwareQueueRepositoryInterface extends QueueRepositoryInterface
{
    /**
     * Marks the item as failed if it is still in processing with the claim
     * token of `$item`.
     *
     * @return bool whether the item was marked as failed; false if it does
     *              not exist, has no claim token, or is not claimed with it
     */
    public function failClaimed(QueueItem $item): bool;
}
