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

interface MessageContainerInterface
{
    public function addMessage(MessageInterface $message): void;
    /**
     * @param array<\CPSIT\ImportExportCore\Messaging\MessageInterface> $messages
     */
    public function addMessages(array $messages): void;
    /**
     * @return array<\CPSIT\ImportExportCore\Messaging\MessageInterface>
     */
    public function getMessages(): array;
    public function hasMessages(): bool;
    public function clearMessages(): void;
    public function hasMessageWithId(int $id): bool;
}
