<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Message class
 */
class Message implements MessageInterface
{
    // Severity constants
    public const SEVERITY_OK = 0;
    public const SEVERITY_INFO = 1;
    public const SEVERITY_NOTICE = 2;
    public const SEVERITY_WARNING = 3;
    public const SEVERITY_ERROR = 4;

    /**
     * @param string $message Message content
     * @param string $title Message title
     * @param int $severity Message severity
     * @param int|null $id Optional message ID
     * @param array|null $additionalInformation Optional additional information
     */
    public function __construct(
        protected string $message,
        protected string $title,
        protected int $severity = self::SEVERITY_OK,
        protected ?int $id = null,
        protected ?array $additionalInformation = null
    ) {
    }

    /**
     * Returns the message content
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the message title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the message severity
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * Returns the message ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns additional information
     */
    public function getAdditionalInformation(): ?array
    {
        return $this->additionalInformation;
    }
}