<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Messaging;

/**
 * Interface for messages
 */
interface MessageInterface
{
    /**
     * Returns the message content
     */
    public function getMessage(): string;

    /**
     * Returns the message title
     */
    public function getTitle(): string;

    /**
     * Returns the message severity
     */
    public function getSeverity(): int;

    /**
     * Returns the message ID
     */
    public function getId(): ?int;

    /**
     * Returns additional information
     */
    public function getAdditionalInformation(): ?array;
}