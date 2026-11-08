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

use CPSIT\ImportExportCore\Messaging\MessageContainer;
use CPSIT\ImportExportCore\Messaging\MessageContainerInterface;
use CPSIT\ImportExportCore\Messaging\MessageContainerTrait;

class TaskResult implements \Iterator, \Countable
{
    use MessageContainerTrait;
    protected int $position = 0;
    protected array $list = [];
    protected int $size = 0;
    protected mixed $info = null;

    /**
     * TaskResult constructor.
     */
    public function __construct(?MessageContainerInterface $messageContainer = null)
    {
        $this->list = [];
        $this->position = 0;
        $this->size = 0;
        $this->info = null;
        $this->messageContainer = $messageContainer ?? new MessageContainer();
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->list[$this->position];
    }

    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function key(): int
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        return isset($this->list[$this->position]);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->size;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function setElements(array $elements): void
    {
        $this->list = $elements;
        $this->size = \count($elements);
        $this->rewind();
    }

    public function add(mixed $newElement): void
    {
        $this->list[] = $newElement;
        ++$this->size;
    }

    /**
     * @return bool
     */
    public function removeElement(mixed $element): bool
    {
        $key = array_search($element, $this->list);
        if ($key !== false) {
            return $this->removeIndex($key);
        }
        return false;
    }

    /**
     * @param int $index
     * @return bool
     */
    public function removeIndex(int $index): bool
    {
        if ($this->size > $index) {
            unset($this->list[$index]);
            --$this->size;
            // if remove element after current
            if ($index >= $this->position && $this->position > 0) {
                --$this->position;
            }
            // reindex list
            $this->list = array_values($this->list);
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->list;
    }

    public function setInfo(mixed $mixed): void
    {
        $this->info = $mixed;
    }

    public function getInfo(): mixed
    {
        return $this->info;
    }
}
