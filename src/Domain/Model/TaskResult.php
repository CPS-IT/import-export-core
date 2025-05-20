<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Domain\Model;

use CPSIT\ImportExportCore\Messaging\MessageContainer;
use CPSIT\ImportExportCore\Messaging\MessageContainerInterface;

class TaskResult implements \Iterator
{
    protected int $position = 0;
    protected array $list = [];
    protected int $size = 0;
    protected mixed $info = null;
    protected MessageContainerInterface $messageContainer;

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
        $this->size = count($elements);
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

    /**
     * Returns all messages from the message container
     */
    public function getMessages(): array
    {
        return $this->messageContainer->getMessages();
    }

    /**
     * Returns and purges all messages from the message container
     */
    public function getAndPurgeMessages(): array
    {
        $messages = $this->messageContainer->getMessages();
        $this->messageContainer->clear();

        return $messages;
    }

    /**
     * Adds all messages.
     * Existing messages are kept.
     */
    public function addMessages(array $messages): void
    {
        $this->messageContainer->addMessages($messages);
    }
}