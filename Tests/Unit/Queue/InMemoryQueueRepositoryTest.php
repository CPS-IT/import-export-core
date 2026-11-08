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

namespace CPSIT\ImportExportCore\Tests\Unit\Queue;

use CPSIT\ImportExportCore\Queue\InMemoryQueueRepository;
use CPSIT\ImportExportCore\Queue\QueueItem;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use PHPUnit\Framework\TestCase;

class InMemoryQueueRepositoryTest extends TestCase
{
    private InMemoryQueueRepository $subject;

    protected function setUp(): void
    {
        $this->subject = new InMemoryQueueRepository();
    }

    public function testEnqueueSkipsDuplicates(): void
    {
        self::assertTrue($this->subject->enqueue('news', ['id' => 1]));
        self::assertFalse($this->subject->enqueue('news', ['id' => 1]));
        self::assertTrue($this->subject->enqueue('events', ['id' => 1]));

        self::assertSame(1, $this->subject->count('news', QueueItemStatus::New));
    }

    public function testClaimReturnsNewItemsInOrderAndMarksThemProcessing(): void
    {
        $this->subject->enqueue('news', ['id' => 1]);
        $this->subject->enqueue('news', ['id' => 2]);
        $this->subject->enqueue('news', ['id' => 3]);

        $first = $this->subject->claim('news', 2);
        $second = $this->subject->claim('news', 2);

        self::assertSame([['id' => 1], ['id' => 2]], array_map(static fn (QueueItem $item): array => $item->data, $first));
        self::assertSame(QueueItemStatus::Processing, $first[0]->status);
        self::assertSame('news', $first[0]->queue);
        self::assertSame([['id' => 3]], array_map(static fn (QueueItem $item): array => $item->data, $second));
        self::assertSame([], $this->subject->claim('news', 2));
        self::assertSame(3, $this->subject->count('news', QueueItemStatus::Processing));
    }

    public function testCompleteAndFail(): void
    {
        $this->subject->enqueue('news', ['id' => 1]);
        $this->subject->enqueue('news', ['id' => 2]);
        [$first, $second] = $this->subject->claim('news', 2);

        $this->subject->complete($first);
        $this->subject->fail($second);

        self::assertSame(1, $this->subject->count('news', QueueItemStatus::Finished));
        self::assertSame(1, $this->subject->count('news', QueueItemStatus::Failed));
        self::assertSame(0, $this->subject->count('news', QueueItemStatus::Processing));
    }

    public function testReleaseSetsProcessingItemsBackToNew(): void
    {
        $this->subject->enqueue('news', ['id' => 1]);
        $this->subject->enqueue('news', ['id' => 2]);
        $this->subject->claim('news', 1);

        self::assertSame(1, $this->subject->release('news'));
        self::assertSame(2, $this->subject->count('news', QueueItemStatus::New));
        self::assertSame(0, $this->subject->release('other'));
    }

    public function testRequeueSetsExistingItemBackToNew(): void
    {
        $this->subject->enqueue('news', ['id' => 1]);
        [$item] = $this->subject->claim('news', 1);
        $this->subject->complete($item);

        self::assertTrue($this->subject->enqueue('news', ['id' => 1], true));
        self::assertSame(1, $this->subject->count('news', QueueItemStatus::New));
        self::assertSame(0, $this->subject->count('news', QueueItemStatus::Finished));
    }

    public function testThrowsForUnknownItem(): void
    {
        $now = new \DateTimeImmutable();
        $item = new QueueItem('42', 'news', 'x', [], QueueItemStatus::Processing, $now, $now);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794400101);

        $this->subject->complete($item);
    }
}
