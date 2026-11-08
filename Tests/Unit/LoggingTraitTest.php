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

namespace CPSIT\ImportExportCore\Tests\Unit;

use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class for testing LoggingTrait
 */
class LoggingTraitTestClass
{
    use LoggingTrait;

    protected const ERROR_CODES = [
        123 => ['Test Error', 'Test error description']
    ];
}

class LoggingTraitWithoutCodesTestClass
{
    use LoggingTrait;
}

class LoggingTraitWithNoticeCodesTestClass
{
    use LoggingTrait;

    protected const NOTICE_CODES = [
        456 => ['Test notice', 'Test notice description'],
    ];
}

/**
 * Class LoggingTraitTest
 */
class LoggingTraitTest extends TestCase
{
    protected LoggingTraitTestClass $subject;
    protected MessageContainer|MockObject $messageContainer;

    protected function setUp(): void
    {
        $this->messageContainer = $this->createMock(MessageContainer::class);
        $this->subject = new LoggingTraitTestClass();
        $this->subject->setMessageContainer($this->messageContainer);
    }

    #[Test]
    public function testLogErrorCreatesDefaultMessage(): void
    {
        $errorId = 123;

        $this->messageContainer->expects($this->once())
            ->method('addMessage')
            ->with($this->callback(function (Message $message) {
                return $message->getTitle() === 'Test Error'
                    && $message->getSeverity() === Message::SEVERITY_ERROR;
            }));

        $this->subject->logError($errorId);
    }

    #[Test]
    public function testGetNoticeCodesInitiallyReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->subject->getNoticeCodes());
    }

    #[Test]
    public function getErrorCodesReturnsEmptyArrayIfClassDefinesNoErrorCodes(): void
    {
        $subject = new LoggingTraitWithoutCodesTestClass();

        $this->assertSame([], $subject->getErrorCodes());
    }

    #[Test]
    public function getNoticeCodesReturnsNoticeCodesConstant(): void
    {
        $subject = new LoggingTraitWithNoticeCodesTestClass();

        $this->assertSame(
            [456 => ['Test notice', 'Test notice description']],
            $subject->getNoticeCodes()
        );
    }

    #[Test]
    public function logErrorWorksWithoutInjectedMessageContainer(): void
    {
        $subject = new LoggingTraitTestClass();
        $subject->logError(123);

        $messages = $subject->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame(Message::SEVERITY_ERROR, $messages[0]->getSeverity());
    }
}
