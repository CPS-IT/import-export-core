<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

use CPSIT\ImportExportCore\Messaging\Message;

class MessageContainer implements MessageContainerInterface
{
    protected array $messages = [];

    public function addMessage(MessageInterface $message): void
    {
        $this->messages[] = $message;

    }

    /**
     * Add messages to list of messages
     *
     * @param array $messages
     */
    public function addMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $this->addMessage($message);
        }
    }

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
    public function hasMessageWithId($id): bool
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
