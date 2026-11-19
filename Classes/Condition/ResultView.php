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

namespace CPSIT\ImportExportCore\Condition;

use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Read-only view of a result, passed to expressions as `result`, so that an
 * expression cannot change the result of a task.
 */
final readonly class ResultView
{
    public function __construct(private ResultInterface $result)
    {
    }

    public function hasMessageWithId(int $id): bool
    {
        return $this->result->hasMessageWithId($id);
    }

    /**
     * @return list<int> IDs of the messages of the result, without messages that have none
     */
    public function getMessageIds(): array
    {
        $ids = [];
        foreach ($this->result->getMessages() as $message) {
            if ($message->getId() !== null) {
                $ids[] = $message->getId();
            }
        }

        return $ids;
    }

    /**
     * Info of the result, if it is a scalar or null; null otherwise
     */
    public function getInfo(): int|float|string|bool|null
    {
        $info = $this->result->getInfo();

        return \is_scalar($info) ? $info : null;
    }

    public function getRead(): int
    {
        return $this->result->getStatistics()->getRead();
    }

    public function getPersisted(): int
    {
        return $this->result->getStatistics()->getPersisted();
    }

    public function getSkipped(): int
    {
        return $this->result->getStatistics()->getSkipped();
    }
}
