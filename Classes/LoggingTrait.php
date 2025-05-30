<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore;

use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageContainerTrait;

/**
 * Trait LoggingTrait
 * Provides logging capabilities
 */
trait LoggingTrait
{
    use MessageContainerTrait;

    /**
     * Returns error codes for current component.
     * Must be an array in the form
     * [
     *  <id> => ['errorTitle', 'errorDescription']
     * ]
     * 'errorDescription' may contain placeholder (%s) for arguments.
     */
    public function getErrorCodes(): array
    {
        return static::ERROR_CODES ?? [];
    }

    /**
     * Returns notice codes for current component.
     * Override this method in instances with actual codes.
     * Must be an array in the form
     * [
     *  <id> => ['title', 'description']
     * ]
     * 'description' may contain placeholder (%s) for arguments.
     */
    public function getNoticeCodes(): array
    {
        return [];
    }

    /**
     * Creates an error message and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logError($id, ?array $arguments = null, ?array $additionalInformation = null): void
    {
        $codes = $this->getErrorCodes();
        $description = $this->renderDescription($id, $codes, $arguments, LoggingInterface::ERROR_UNKNOWN_MESSAGE);
        $title = $this->renderTitle($id, $codes, LoggingInterface::ERROR_UNKNOWN_TITLE);

        $this->logMessage($title, $description, Message::SEVERITY_ERROR, $id, $additionalInformation);
    }

    /**
     * Creates a notice and adds it to the message container
     *
     * @param int $id Error id
     * @param array|null $arguments Optional arguments. Will be used as arguments for formatted message.
     * @param array|null $additionalInformation Optional array with additional information
     */
    public function logNotice($id, ?array $arguments = null, ?array $additionalInformation = null): void
    {
        $codes = $this->getNoticeCodes();
        $title = $this->renderTitle($id, $codes, LoggingInterface::NOTICE_UNKNOWN_TITLE);
        $description = $this->renderDescription($id, $codes, $arguments, LoggingInterface::NOTICE_UNKNOWN_MESSAGE);

        $this->logMessage($title, $description, Message::SEVERITY_NOTICE, $id, $additionalInformation);
    }

    /**
     * Logs a message
     *
     * @param string $title Message title
     * @param string $description Message content
     * @param int $severity Message severity
     * @param int|null $id Optional message ID
     * @param array|null $additionalInformation Optional additional information
     */
    public function logMessage($title, $description, $severity = Message::SEVERITY_OK, $id = null, ?array $additionalInformation = null): void
    {
        $message = new Message(
            $description,
            $title,
            $severity,
            $id,
            $additionalInformation
        );
        $this->messageContainer->addMessage($message);
    }

    /**
     * Renders a description
     *
     * @param int $id
     * @param array $codes An array of codes.
     * @param array|null $arguments Optional arguments
     * @param string $default Default description
     */
    protected function renderDescription($id, $codes, ?array $arguments, $default = LoggingInterface::DEFAULT_UNKNOWN_MESSAGE): string
    {
        $description = $default;
        if (isset($codes[$id])) {
            $description = $codes[$id][1];
            if ($arguments !== null) {
                array_unshift($arguments, $description);
                $description = \sprintf(...$arguments);
            }
        }

        $description .= PHP_EOL . 'Message ID ' . $id . ' in component ' . $this::class;

        return $description;
    }

    /**
     * Renders a title
     *
     * @param int $id Message ID
     * @param array $codes An array of codes.
     * @param string $default Default title
     * @return string
     */
    public function renderTitle($id, array $codes, $default = LoggingInterface::DEFAULT_MESSAGE_TITLE): string
    {
        if (isset($codes[$id])) {
            $title = $codes[$id][0];
            return $title;
        }

        return $default;
    }
}
