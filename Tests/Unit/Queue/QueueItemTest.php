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

use CPSIT\ImportExportCore\Queue\QueueItem;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use PHPUnit\Framework\TestCase;

class QueueItemTest extends TestCase
{
    public function testWithStatusReturnsChangedCopy(): void
    {
        $created = new \DateTimeImmutable('2026-01-01 10:00');
        $updated = new \DateTimeImmutable('2026-01-01 11:00');
        $item = new QueueItem('1', 'news', 'abc', ['id' => 1], QueueItemStatus::New, $created, $created);

        $changed = $item->withStatus(QueueItemStatus::Finished, $updated);

        self::assertSame(QueueItemStatus::New, $item->status);
        self::assertSame(QueueItemStatus::Finished, $changed->status);
        self::assertSame($created, $changed->createdAt);
        self::assertSame($updated, $changed->updatedAt);
        self::assertSame(['1', 'news', 'abc', ['id' => 1]], [$changed->id, $changed->queue, $changed->checksum, $changed->data]);
    }

    public function testChecksumDependsOnQueueAndData(): void
    {
        $checksum = QueueItem::checksum('news', ['id' => 1, 'title' => 'Ä/b']);

        self::assertSame($checksum, QueueItem::checksum('news', ['id' => 1, 'title' => 'Ä/b']));
        self::assertNotSame($checksum, QueueItem::checksum('events', ['id' => 1, 'title' => 'Ä/b']));
        self::assertNotSame($checksum, QueueItem::checksum('news', ['id' => 2, 'title' => 'Ä/b']));
        self::assertSame('{"id":1,"title":"Ä/b","price":1.0}', QueueItem::encode(['id' => 1, 'title' => 'Ä/b', 'price' => 1.0]));
    }

    public function testEncodeThrowsForInvalidData(): void
    {
        $this->expectException(\JsonException::class);

        QueueItem::encode(["\xB1"]);
    }
}
