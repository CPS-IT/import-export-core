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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\Queue;

use CPSIT\ImportExportCore\Dbal\Queue\DbalQueueRepository;
use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Persistence\DataSourceQueue;
use CPSIT\ImportExportCore\Persistence\DataTargetQueue;
use CPSIT\ImportExportCore\Queue\QueueItem;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Service/Fixtures/Components.php';

class DbalQueueRepositoryTest extends TestCase
{
    private Connection $connection;

    private DbalQueueRepository $subject;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->subject = new DbalQueueRepository($this->connection);
        (new SchemaInstaller($this->connection))->install($this->subject);
    }

    /**
     * @param list<QueueItem> $items
     * @return list<array<mixed>>
     */
    private static function data(array $items): array
    {
        return array_map(static fn (QueueItem $item): array => $item->data, $items);
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
        $this->subject->enqueue('news', ['id' => 1, 'title' => 'Ä/ö', 'price' => 1.0]);
        $this->subject->enqueue('news', ['id' => 2]);
        $this->subject->enqueue('news', ['id' => 3]);

        $first = $this->subject->claim('news', 2);
        $second = $this->subject->claim('news', 2);

        self::assertSame([['id' => 1, 'title' => 'Ä/ö', 'price' => 1.0], ['id' => 2]], self::data($first));
        self::assertSame(QueueItemStatus::Processing, $first[0]->status);
        self::assertSame('news', $first[0]->queue);
        self::assertSame(QueueItem::checksum('news', ['id' => 2]), $first[1]->checksum);
        self::assertInstanceOf(\DateTimeImmutable::class, $first[0]->createdAt);
        self::assertSame([['id' => 3]], self::data($second));
        self::assertSame([], $this->subject->claim('news', 2));
        self::assertSame([], $this->subject->claim('news', 0));
        self::assertSame(3, $this->subject->count('news', QueueItemStatus::Processing));
    }

    public function testClaimsEachItemOnceWithSeveralRepositories(): void
    {
        $other = new DbalQueueRepository($this->connection);
        foreach ([1, 2, 3, 4] as $id) {
            $this->subject->enqueue('news', ['id' => $id]);
        }

        $first = $this->subject->claim('news', 3);
        $second = $other->claim('news', 3);

        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], self::data($first));
        self::assertSame([['id' => 4]], self::data($second));
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
        self::assertSame([['id' => 1]], self::data($this->subject->claim('news', 1)));
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

    public function testWorksWithQueueSourceAndTarget(): void
    {
        $fill = new TransferTask();
        $fill->setIdentifier('fill');
        $fill->setSource(new ArraySource([['id' => 1], ['id' => 2], ['id' => 3]]));
        $queueTarget = new DataTargetQueue($this->subject);
        $queueTarget->setConfiguration(['identifier' => 'news']);
        $fill->setTarget($queueTarget);
        $processor = new DataTransferProcessor();
        $processor->process($fill, new TaskResult());

        $drain = new TransferTask();
        $drain->setIdentifier('drain');
        $queueSource = new DataSourceQueue($this->subject);
        $queueSource->setConfiguration(['identifier' => 'news', 'limit' => 2]);
        $drain->setSource($queueSource);
        $target = new InMemoryTarget();
        $drain->setTarget($target);
        $processor->process($drain, new TaskResult());

        self::assertSame([['id' => 1], ['id' => 2]], $target->persisted);
        self::assertSame(2, $this->subject->count('news', QueueItemStatus::Finished));
        self::assertSame(1, $this->subject->count('news', QueueItemStatus::New));
    }
}
