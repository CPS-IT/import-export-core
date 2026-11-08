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

namespace CPSIT\ImportExportCore\Tests\Unit\Messaging;

use CPSIT\ImportExportCore\Messaging\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class MessageTest
 */
class MessageTest extends TestCase
{
    /**
     * @var Message
     */
    protected Message $subject;

    #[Test]
    public function constructorSetsProperties(): void
    {
        $message = 'foo';
        $title = 'bar';
        $severity = Message::SEVERITY_ERROR;
        $id = 12;
        $additionalInformation = ['baz'];

        $this->subject = new Message($message, $title, $severity, $id, $additionalInformation);
        $this->assertSame(
            $message,
            $this->subject->getMessage()
        );

        $this->assertSame(
            $title,
            $this->subject->getTitle()
        );

        $this->assertSame(
            $severity,
            $this->subject->getSeverity()
        );

        $this->assertSame(
            $id,
            $this->subject->getId()
        );

        $this->assertSame(
            $additionalInformation,
            $this->subject->getAdditionalInformation()
        );
    }
    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        $message = 'foo';
        $defaultTitle = '';
        $defaultSeverity = Message::SEVERITY_OK;
        $additionalInformation = [];

        $this->subject = new Message($message, $defaultTitle, $defaultSeverity, null, $additionalInformation);
        $this->assertSame(
            $defaultTitle,
            $this->subject->getTitle()
        );

        $this->assertSame(
            $defaultSeverity,
            $this->subject->getSeverity()
        );

        $this->assertSame(
            $additionalInformation,
            $this->subject->getAdditionalInformation()
        );
        $this->assertNull(
            $this->subject->getId()
        );
    }
}
