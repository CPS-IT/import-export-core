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

    /**
     * Set the additional information
     */
    public function setAdditionalInformation(array $additionalInformation): void
    {
        $this->additionalInformation = $additionalInformation;
    }
}
