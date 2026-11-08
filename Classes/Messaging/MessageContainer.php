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

class MessageContainer implements MessageContainerInterface
{
    /**
     * @var array<\CPSIT\ImportExportCore\Messaging\MessageInterface>
     */
    protected array $messages = [];

    public function addMessage(MessageInterface $message): void
    {
        $this->messages[] = $message;

    }

    /**
     * Add messages to list of messages
     *
     * @param array<\CPSIT\ImportExportCore\Messaging\MessageInterface> $messages
     */
    public function addMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $this->addMessage($message);
        }
    }

    /**
     * @return array<\CPSIT\ImportExportCore\Messaging\MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Tells by id if a container has a certain message
     * Note: not all messages must have an id!
     *
     * @param $id
     * @return bool
     */
    public function hasMessageWithId(int $id): bool
    {
        /** @var Message $message */
        foreach ($this->getMessages() as $message) {
            if ($id === $message->getId()) {
                return true;
            }
        }

        return false;
    }

    public function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    public function clearMessages(): void
    {
        $this->messages = [];
    }
}
