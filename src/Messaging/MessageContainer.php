<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Container for messages
 */
class MessageContainer implements MessageContainerInterface
{
    /**
     * @var array<MessageInterface>
     */
    protected array $messages = [];

    /**
     * Adds a message to the container
     */
    public function addMessage(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Adds multiple messages to the container
     * 
     * @param array<MessageInterface> $messages
     */
    public function addMessages(array $messages): void
    {
        foreach ($messages as $message) {
            if ($message instanceof MessageInterface) {
                $this->addMessage($message);
            }
        }
    }

    /**
     * Returns all messages
     * 
     * @return array<MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Clears all messages from the container
     */
    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * Tells if the container has a message with the given ID
     */
    public function hasMessageWithId($id): bool
    {
        foreach ($this->messages as $message) {
            if ($message->getId() === $id) {
                return true;
            }
        }
        
        return false;
    }
}