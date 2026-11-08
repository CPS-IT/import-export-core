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

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Trait MessageContainerTrait
 * Provides methods for message handling
 */
trait MessageContainerTrait
{
    protected MessageContainerInterface $messageContainer;

    public function __construct(?MessageContainerInterface $messageContainer = null)
    {
        $this->messageContainer = $messageContainer ?? new MessageContainer();
    }

    /**
     * Adds all messages.
     * Existing messages are kept.
     */
    public function addMessages(array $messages): void
    {
        $this->messageContainer->addMessages($messages);
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
