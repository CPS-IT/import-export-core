<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

class MessageContainer implements MessageContainerInterface
{
    protected array $messages = [];

    public function addMessage(string $message, int $severity = 0): void
    {
        $this->messages[] = [
            'message' => $message,
            'severity' => $severity
        ];
    }

    public function getMessages(): array
    {
        return $this->messages;
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