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

namespace CPSIT\ImportExportCore\Tests\Unit\Persistence;

use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Persistence\DataSourceQueue;
use CPSIT\ImportExportCore\Persistence\DataTargetQueue;
use CPSIT\ImportExportCore\Queue\InMemoryQueueRepository;
use CPSIT\ImportExportCore\Queue\QueueItemStatus;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ArraySource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ToObjectConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Service/Fixtures/Components.php';

class QueueSourceAndTargetTest extends TestCase
{
    private InMemoryQueueRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryQueueRepository();
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function sourceConfigurationProvider(): array
    {
        return [
            'identifier only' => [['identifier' => 'news'], true],
            'with limit' => [['identifier' => 'news', 'limit' => 5], true],
            'missing identifier' => [[], false],
            'empty identifier' => [['identifier' => ''], false],
            'limit not an int' => [['identifier' => 'news', 'limit' => '5'], false],
            'limit zero' => [['identifier' => 'news', 'limit' => 0], false],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('sourceConfigurationProvider')]
    public function testValidatesSourceConfiguration(array $configuration, bool $expected): void
    {
        self::assertSame($expected, (new DataSourceQueue($this->repository))->isConfigurationValid($configuration));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function targetConfigurationProvider(): array
    {
        return [
            'identifier only' => [['identifier' => 'news'], true],
            'with allowUpdate' => [['identifier' => 'news', 'allowUpdate' => true], true],
            'missing identifier' => [[], false],
            'allowUpdate not a bool' => [['identifier' => 'news', 'allowUpdate' => '1'], false],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('targetConfigurationProvider')]
    public function testValidatesTargetConfiguration(array $configuration, bool $expected): void
    {
        self::assertSame($expected, (new DataTargetQueue($this->repository))->isConfigurationValid($configuration));
    }

    public function testSourceClaimsAndCompletesItems(): void
    {
        $this->repository->enqueue('news', ['id' => 1]);
        $this->repository->enqueue('news', ['id' => 2]);
        $subject = new DataSourceQueue($this->repository);

        $records = iterator_to_array($subject->getRecords(['identifier' => 'news', 'limit' => 1]), false);

        self::assertSame([['id' => 1]], $records);
        self::assertSame(1, $this->repository->count('news', QueueItemStatus::Processing));

        $subject->complete(new TaskResult());
        $subject->complete(new TaskResult());

        self::assertSame(1, $this->repository->count('news', QueueItemStatus::Finished));
        self::assertSame(1, $this->repository->count('news', QueueItemStatus::New));
    }

    public function testSourceUsesDefaultLimit(): void
    {
        for ($id = 1; $id <= 12; ++$id) {
            $this->repository->enqueue('news', ['id' => $id]);
        }

        $records = iterator_to_array((new DataSourceQueue($this->repository))->getRecords(['identifier' => 'news']), false);

        self::assertCount(DataSourceQueue::DEFAULT_LIMIT, $records);
    }

    public function testTargetEnqueuesRecords(): void
    {
        $subject = new DataTargetQueue($this->repository);
        $subject->setConfiguration(['identifier' => 'news']);

        self::assertTrue($subject->persist(['id' => 1]));
        self::assertFalse($subject->persist(['id' => 1]));
        self::assertTrue($subject->persist(['id' => 1], ['identifier' => 'news', 'allowUpdate' => true]));
        self::assertSame(1, $this->repository->count('news', QueueItemStatus::New));
    }

    public function testTargetRejectsObjects(): void
    {
        $subject = new DataTargetQueue($this->repository);
        $subject->setConfiguration(['identifier' => 'news']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794400111);

        $subject->persist(new \stdClass());
    }

    public function testTargetRequiresQueueIdentifier(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794400112);

        (new DataTargetQueue($this->repository))->persist(['id' => 1]);
    }

    public function testStagedTransfer(): void
    {
        $processor = new DataTransferProcessor();

        $fill = new TransferTask();
        $fill->setIdentifier('fill');
        $fill->setSource(new ArraySource([['id' => 1], ['id' => 2], ['id' => 3]]));
        $queueTarget = new DataTargetQueue($this->repository);
        $queueTarget->setConfiguration(['identifier' => 'news']);
        $fill->setTarget($queueTarget);

        $target = new InMemoryTarget();
        $drain = new TransferTask();
        $drain->setIdentifier('drain');
        $queueSource = new DataSourceQueue($this->repository);
        $queueSource->setConfiguration(['identifier' => 'news', 'limit' => 2]);
        $drain->setSource($queueSource);
        $drain->setTarget($target);

        $processor->process($fill);
        $processor->process($fill);
        self::assertSame(3, $this->repository->count('news', QueueItemStatus::New));

        $processor->process($drain);
        self::assertSame([['id' => 1], ['id' => 2]], $target->persisted);
        self::assertSame(2, $this->repository->count('news', QueueItemStatus::Finished));

        $processor->process($drain);
        $processor->process($drain);
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $target->persisted);
        self::assertSame(3, $this->repository->count('news', QueueItemStatus::Finished));
    }

    public function testItemsStayInProcessingIfTaskFails(): void
    {
        $this->repository->enqueue('news', ['id' => 1]);
        $task = new TransferTask();
        $task->setIdentifier('drain');
        $source = new DataSourceQueue($this->repository);
        $source->setConfiguration(['identifier' => 'news']);
        $task->setSource($source);
        $target = new DataTargetQueue($this->repository);
        $target->setConfiguration(['identifier' => 'other']);
        $task->setTarget($target);
        $task->setConverters([new ToObjectConverter()]);

        try {
            (new DataTransferProcessor())->process($task);
            self::fail('Expected exception');
        } catch (\UnexpectedValueException) {
        }

        self::assertSame(1, $this->repository->count('news', QueueItemStatus::Processing));
        self::assertSame(1, $this->repository->release('news'));
    }
}
