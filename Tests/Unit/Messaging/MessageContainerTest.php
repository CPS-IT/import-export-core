<?php

declare(strict_types=1);
namespace CPSIT\ImportExportCore\Tests\Unit\Messaging;

/**
 * Copyright notice
 * (c) 2017. Dirk Wenzel <wenzel@cps-it.de>
 * All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 */
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageContainer;
use CPSIT\ImportExportCore\Messaging\MessageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MessageContainerTest
 */
class MessageContainerTest extends TestCase
{
    protected MessageContainer $subject;
    protected MessageInterface|MockObject $message;

    protected function setUp(): void
    {
        $this->subject = new MessageContainer();
        $this->message = $this->getMockBuilder(MessageInterface::class)->getMock();
    }

    public function testAddMessageStoresMessageAndSeverity(): void
    {
        $this->subject->addMessage($this->message);
        $this->message->expects($this->once())->method('getSeverity')->willReturn(1);

        $messages = $this->subject->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->message, $messages[0]);
        $this->assertEquals(1, $messages[0]->getSeverity());
    }

    public function testHasMessagesReturnsFalseInitially(): void
    {
        $this->assertFalse($this->subject->hasMessages());
    }

    public function testHasMessagesReturnsTrueAfterAddingMessage(): void
    {
        $this->subject->addMessage($this->message);
        $this->assertTrue($this->subject->hasMessages());
    }

    public function testClearMessagesRemovesAllMessages(): void
    {
        $this->subject->addMessage($this->message);
        $this->subject->clearMessages();
        $this->assertFalse($this->subject->hasMessages());
        $this->assertEmpty($this->subject->getMessages());
    }

    #[Test]
    public function getMessagesInitiallyReturnsEmptyArray(): void
    {
        $expected = [];
        $this->assertSame(
            $expected,
            $this->subject->getMessages()
        );
    }

    #[Test]
    public function singleMessageCanBeAdded(): void
    {
        /** @var Message $message */
        $message = new Message('fooMessage', 'barTitle');
        $this->subject->addMessage($message);

        $expected = [$message];

        $this->assertSame(
            $expected,
            $this->subject->getMessages()
        );
    }

    #[Test]
    public function multipleMessagesCanBeAdded(): void
    {
        /** @var Message $message */
        $message = $this->createMock(Message::class);

        $messages = [$message];
        $this->subject->addMessages($messages);
        $this->assertSame(
            $messages,
            $this->subject->getMessages()
        );
    }

    #[Test]
    public function messagesCanBeCleared(): void
    {
        $message = new Message('fooMessage', 'barTitle');
        $expected = [];
        $this->subject->addMessages([$message]);
        $this->subject->clearMessages();

        $this->assertSame(
            $expected,
            $this->subject->getMessages()
        );
    }

    #[Test]
    public function hasMessageInitiallyReturnsFalse(): void
    {
        $nonExistingId = 4447;
        $this->subject->clearMessages();
        $this->assertFalse(
            $this->subject->hasMessageWithId($nonExistingId)
        );
    }

    #[Test]
    public function hasMessageReturnsTrueForMessageInContainer(): void
    {
        $id = 7;
        $mockMessage = $this->createMock(Message::class);
        $mockMessage->method('getId')
            ->willReturn($id);
        $this->subject->addMessage($mockMessage);
        $this->assertTrue(
            $this->subject->hasMessageWithId($id)
        );
    }
}
