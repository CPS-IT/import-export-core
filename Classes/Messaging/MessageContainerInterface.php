<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

interface MessageContainerInterface
{
    public function addMessage(MessageInterface $message): void;
    public function addMessages(array $messages): void;
    public function getMessages(): array;
    public function hasMessages(): bool;
    public function clearMessages(): void;
    public function hasMessageWithId($id): bool;
}
