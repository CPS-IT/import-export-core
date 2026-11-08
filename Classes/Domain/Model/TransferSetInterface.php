<?php declare(strict_types=1);

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

/**
 * Class TransferSet
 * A set of transfer tasks
 */
interface TransferSetInterface
{
    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     */
    public function setDescription(string $description): void;

    /**
     * @return TransferTaskInterface[]
     */
    public function getTasks(): array;

    /**
     * @param TransferTaskInterface[] $tasks
     */
    public function setTasks(array $tasks): void;

    /**
     * Gets the label
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Sets the label
     *
     * @param string $label
     */
    public function setLabel(string $label): void;
}
