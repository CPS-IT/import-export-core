<?php
declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Messaging;

use CPSIT\ImportExportCore\Messaging\MessageContainer;
use PHPUnit\Framework\TestCase;

class MessageContainerTest extends TestCase
{
    protected MessageContainer $subject;

    protected function setUp(): void
    {
        $this->subject = new MessageContainer();
    }

    public function testAddMessageStoresMessageAndSeverity(): void
    {
        $this->subject->addMessage('test message', 1);

        $messages = $this->subject->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('test message', $messages[0]['message']);
        $this->assertEquals(1, $messages[0]['severity']);
    }

    public function testHasMessagesReturnsFalseInitially(): void
    {
        $this->assertFalse($this->subject->hasMessages());
    }

    public function testHasMessagesReturnsTrueAfterAddingMessage(): void
    {
        $this->subject->addMessage('test message');
        $this->assertTrue($this->subject->hasMessages());
    }

    public function testClearMessagesRemovesAllMessages(): void
    {
        $this->subject->addMessage('test message');
        $this->subject->clearMessages();
        $this->assertFalse($this->subject->hasMessages());
        $this->assertEmpty($this->subject->getMessages());
    }
}
