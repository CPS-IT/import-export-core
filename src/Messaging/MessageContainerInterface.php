<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Interface for message containers
 */
interface MessageContainerInterface
{
    /**
     * Adds a message to the container
     * 
     * @param MessageInterface $message
     */
    public function addMessage(MessageInterface $message): void;

    /**
     * Adds multiple messages to the container
     * 
     * @param array<MessageInterface> $messages
     */
    public function addMessages(array $messages): void;

    /**
     * Returns all messages
     * 
     * @return array<MessageInterface>
     */
    public function getMessages(): array;

    /**
     * Clears all messages from the container
     */
    public function clear(): void;

    /**
     * Tells if the container has a message with the given ID
     */
    public function hasMessageWithId($id): bool;
}