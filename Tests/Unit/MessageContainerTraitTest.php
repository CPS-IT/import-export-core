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

use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageContainer;
use CPSIT\ImportExportCore\Messaging\MessageContainerTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MessageContainerTraitTest
 */
class MessageContainerTraitTest extends TestCase
{
    /**
     * subject
     * @var MessageContainerTrait&MockObject
     */
    protected $subject;

    /**
     * @var MessageContainer&MockObject
     */
    protected $messageContainer;

    /**
     * set up subject
     */
    protected function setUp(): void
    {
        // Create message container mock directly
        $this->messageContainer = $this->createMock(MessageContainer::class);

        // In PHPUnit 12, getMockForTrait is removed - use anonymous class instead
        $this->subject = new class() {
            use MessageContainerTrait;
        };
        $this->subject->setMessageContainer($this->messageContainer);
    }

    #[Test]
    public function testGetMessagesReturnsMessagesFromContainer(): void
    {
        $messages = ['foo'];
        $this->messageContainer->expects($this->once())
            ->method('getMessages')->willReturn($messages);
        $this->assertSame(
            $messages,
            $this->subject->getMessages()
        );
    }

    #[Test]
    public function testHasMessageWithIdReturnsResultFromMessageContainter(): void
    {
        $id = 123;
        $this->messageContainer->expects($this->once())
            ->method('hasMessageWithId')
            ->with($id)
            ->willReturn(true);

        $this->assertTrue(
            $this->subject->hasMessageWithId($id)
        );
    }

    #[Test]
    public function messagesCanBeAddedInClassWithOwnConstructor(): void
    {
        $subject = new class('some value') {
            use MessageContainerTrait;

            public function __construct(public string $value)
            {
            }
        };
        $message = new Message('message', 'title');

        $subject->addMessages([$message]);

        $this->assertSame([$message], $subject->getMessages());
    }
}
