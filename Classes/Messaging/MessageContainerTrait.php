<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Trait MessageContainerTrait
 * Provides methods for message handling
 */
trait MessageContainerTrait
{
    protected MessageContainer $messageContainer;

    public function __construct(?MessageContainer $messageContainer = null)
    {
        $this->messageContainer = $messageContainer ?? new MessageContainer();
    }

    /**
     * Returns all messages.
     * Messages are kept.
     * @return array<MessageInterface>
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
        $this->messageContainer->clearMessages();

        return $messages;
    }

    /**
     * Tells by id if a container has a certain message
     * Note: not all messages must have an id!
     */
    public function hasMessageWithId($id): bool
    {
        return $this->messageContainer->hasMessageWithId($id);
    }
}
