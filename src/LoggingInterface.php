<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore;

/**
 * Interface for components that provide logging functionality
 */
interface LoggingInterface
{
    public const DEFAULT_MESSAGE_TITLE = 'Message';
    public const DEFAULT_UNKNOWN_MESSAGE = 'Message with unknown ID';
    public const ERROR_UNKNOWN_MESSAGE = 'An unknown error occurred';
    public const ERROR_UNKNOWN_TITLE = 'Unknown error';
    public const NOTICE_UNKNOWN_MESSAGE = 'Notice with unknown ID';
    public const NOTICE_UNKNOWN_TITLE = 'Notice';

    /**
     * Gets all messages
     * @return array
     */
    public function getMessages(): array;

    /**
     * Returns and purges all messages from the message container
     */
    public function getAndPurgeMessages(): array;
}