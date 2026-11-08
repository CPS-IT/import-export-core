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

namespace CPSIT\ImportExportCore\Tests\Unit\Factory;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Factory\ComponentFactory;
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Factory\TransferTaskFactory;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\AppendingInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkingPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ObjectConverter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RecordingFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';
require_once __DIR__ . '/../Service/Fixtures/Components.php';

class TransferTaskFactoryTest extends TestCase
{
    private TransferTaskFactory $subject;

    protected function setUp(): void
    {
        $registry = new ComponentRegistry();
        $registry->register(DataSourceInterface::class, 'test', IdentifiableSource::class);
        $registry->setDefault(DataTargetInterface::class, InMemoryTarget::class);
        $this->subject = new TransferTaskFactory(new ComponentFactory($registry));
    }

    public function testCreatesTask(): void
    {
        $task = $this->subject->create('import-news', [
            'label' => 'News',
            'description' => 'Imports news',
            'class' => \stdClass::class,
            'source' => ['class' => 'test', 'identifier' => 'legacy', 'config' => ['table' => 'news']],
            'target' => ['config' => ['table' => 'tx_news']],
            'initializers' => [['class' => AppendingInitializer::class]],
            'preProcessors' => [
                '20' => ['class' => SetFieldPreProcessor::class, 'config' => ['field' => 'b']],
                '10' => ['class' => SetFieldPreProcessor::class, 'config' => ['field' => 'a']],
            ],
            'converters' => [['class' => ObjectConverter::class]],
            'postProcessors' => [['class' => MarkingPostProcessor::class]],
            'finishers' => [['class' => RecordingFinisher::class]],
        ]);

        self::assertSame('import-news', $task->getIdentifier());
        self::assertSame('News', $task->getLabel());
        self::assertSame('Imports news', $task->getDescription());
        self::assertSame(\stdClass::class, $task->getTargetClass());

        $source = $task->getSource();
        self::assertInstanceOf(IdentifiableSource::class, $source);
        self::assertSame('legacy', $source->getIdentifier());
        self::assertSame(['table' => 'news'], $source->getConfiguration());

        $target = $task->getTarget();
        self::assertInstanceOf(InMemoryTarget::class, $target);
        self::assertSame(['table' => 'tx_news'], $target->getConfiguration());

        self::assertInstanceOf(AppendingInitializer::class, $task->getInitializers()[0]);
        self::assertSame([20, 10], array_keys($task->getPreProcessors()));
        self::assertSame(['field' => 'a'], $task->getPreProcessors()[10]->getConfiguration());
        self::assertInstanceOf(ObjectConverter::class, $task->getConverters()[0]);
        self::assertInstanceOf(MarkingPostProcessor::class, $task->getPostProcessors()[0]);
        self::assertInstanceOf(RecordingFinisher::class, $task->getFinishers()[0]);
    }

    public function testComponentListsAreOptional(): void
    {
        $task = $this->subject->create('minimal', ['source' => ['class' => 'test'], 'target' => []]);

        self::assertSame([], $task->getInitializers());
        self::assertSame([], $task->getPreProcessors());
        self::assertSame([], $task->getConverters());
        self::assertSame([], $task->getPostProcessors());
        self::assertSame([], $task->getFinishers());
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function invalidSettingsProvider(): array
    {
        return [
            'source missing' => [['target' => []], TransferTaskFactory::MISSING_SOURCE_EXCEPTION_CODE],
            'source not an array' => [['source' => 'test', 'target' => []], TransferTaskFactory::MISSING_SOURCE_EXCEPTION_CODE],
            'target missing' => [['source' => ['class' => 'test']], TransferTaskFactory::MISSING_TARGET_EXCEPTION_CODE],
            'list not an array' => [['source' => ['class' => 'test'], 'target' => [], 'finishers' => 'foo'], 1794230021],
            'list entry not an array' => [['source' => ['class' => 'test'], 'target' => [], 'finishers' => ['foo']], 1794230022],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('invalidSettingsProvider')]
    public function testThrowsForInvalidSettings(array $settings, int $code): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage('"broken"');

        $this->subject->create('broken', $settings);
    }
}
