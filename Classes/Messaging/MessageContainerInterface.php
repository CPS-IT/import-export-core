<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

interface MessageContainerInterface
{
    public function addMessage(MessageInterface $message): void;
    public function getMessages(): array;
    public function hasMessages(): bool;
    public function clearMessages(): void;
}
