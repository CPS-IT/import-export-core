<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Messaging;

use CPSIT\ImportExportCore\Messaging\MessageContainer;
use CPSIT\ImportExportCore\Messaging\MessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals(1, $messages[0]->getSeverity());;
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
        $this->subject->addMessage($this->message);;
        $this->subject->clearMessages();
        $this->assertFalse($this->subject->hasMessages());
        $this->assertEmpty($this->subject->getMessages());
    }
}
