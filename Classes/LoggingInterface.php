<?php declare(strict_types=1);

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

namespace CPSIT\ImportExportCore;

use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageInterface;

/**
 * Components that report errors, notices and other messages.
 *
 * Messages are collected in a message container. Error and notice codes map a
 * message id to a title and a description, which may contain placeholders
 * (%s) for arguments.
 */
interface LoggingInterface
{
    public const ERROR_UNKNOWN_MESSAGE = 'Unknown message';
    public const ERROR_UNKNOWN_TITLE = 'Unknown title';
    public const NOTICE_UNKNOWN_MESSAGE = 'Unknown message';
    public const NOTICE_UNKNOWN_TITLE = 'Unknown title';
    public const DEFAULT_MESSAGE_TITLE = 'Unknown message title';
    public const DEFAULT_UNKNOWN_MESSAGE = 'Unknown message';
    /**
     * Returns error codes for current component.
     * Must be an array in the form
     * [
     *  <id> => ['errorTitle', 'errorDescription']
     * ]
     * 'errorDescription' may contain placeholder (%s) for arguments.
     * @return array
     */
    public function getErrorCodes(): array;

    /**
     * Returns notice codes for current component.
     * Override this method in instances with actual codes.
     * Must be an array in the form
     * [
     *  <id> => ['title', 'description']
     * ]
     * 'description' may contain placeholder (%s) for arguments.
     */
    public function getNoticeCodes(): array;

    /**
     * Creates an error message and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logError($id, ?array $arguments = null, ?array $additionalInformation = null): void;

    /**
     * Creates a notice and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logNotice($id, ?array $arguments = null, ?array $additionalInformation = null): void;

    /**
     * Logs a message
     *
     * @param string $title Message title
     * @param string $description Message content
     * @param int $severity Message severity
     * @param int|null $id Optional message ID
     * @param array|null $additionalInformation Optional additional information
     */
    public function logMessage($title, $description, $severity = Message::SEVERITY_OK, $id = null, ?array $additionalInformation = null): void;

    /**
     * Renders a title
     *
     * @param int $id Message ID
     * @param array $codes An array of codes.
     * @param string $default Default title
     * @return string
     */
    public function renderTitle($id, array $codes, $default = \CPSIT\ImportExportCore\LoggingInterface::DEFAULT_MESSAGE_TITLE): string;

    /**
     * Returns all messages.
     * Messages are kept.
     * @return array<MessageInterface>
     */
    public function getMessages(): array;

    /**
     * Returns and purges all messages from the message container
     */
    public function getAndPurgeMessages(): array;

    /**
     * Tells by id if a container has a certain message
     * Note: not all messages must have an id!
     */
    public function hasMessageWithId($id): bool;
}
