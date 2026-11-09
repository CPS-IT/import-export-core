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
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyConverterAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyFinisherAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyInitializerAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyPostProcessorAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyPreProcessorAdapter;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\AppendingInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\InMemoryTarget;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\LogInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkingPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ObjectConverter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RecordingFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RejectingFilter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ResultFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ToObjectConverter;
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
            'initializers' => [['class' => LogInitializer::class]],
            'preProcessors' => [
                '20' => ['class' => FieldPreProcessor::class, 'config' => ['field' => 'b']],
                '10' => ['class' => FieldPreProcessor::class, 'config' => ['field' => 'a']],
            ],
            'converters' => [['class' => ToObjectConverter::class]],
            'postProcessors' => [['class' => MarkPostProcessor::class]],
            'finishers' => [['class' => ResultFinisher::class]],
            'filters' => [['class' => RejectingFilter::class]],
            'idMap' => ['namespace' => 'tx_news', 'sourceIdField' => 'uid'],
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

        self::assertInstanceOf(LogInitializer::class, $task->getInitializers()[0]);
        self::assertSame([20, 10], array_keys($task->getPreProcessors()));
        $preProcessor = $task->getPreProcessors()[10];
        self::assertInstanceOf(FieldPreProcessor::class, $preProcessor);
        self::assertSame(['field' => 'a'], $preProcessor->getConfiguration());
        self::assertInstanceOf(ToObjectConverter::class, $task->getConverters()[0]);
        self::assertInstanceOf(MarkPostProcessor::class, $task->getPostProcessors()[0]);
        self::assertInstanceOf(ResultFinisher::class, $task->getFinishers()[0]);
        self::assertInstanceOf(RejectingFilter::class, $task->getFilters()[0]);
        self::assertSame('tx_news', $task->getIdMapSettings()->namespace);
        self::assertSame('uid', $task->getIdMapSettings()->sourceIdField);
    }

    public function testWrapsDeprecatedComponents(): void
    {
        $task = $this->subject->create('legacy', [
            'source' => ['class' => 'test'],
            'target' => [],
            'initializers' => [['class' => AppendingInitializer::class]],
            'preProcessors' => [['class' => SetFieldPreProcessor::class, 'config' => ['field' => 'a']]],
            'converters' => [['class' => ObjectConverter::class]],
            'postProcessors' => [['class' => MarkingPostProcessor::class]],
            'finishers' => [['class' => RecordingFinisher::class]],
        ]);

        self::assertInstanceOf(LegacyInitializerAdapter::class, $task->getInitializers()[0]);
        $preProcessor = $task->getPreProcessors()[0];
        self::assertInstanceOf(LegacyPreProcessorAdapter::class, $preProcessor);
        self::assertInstanceOf(SetFieldPreProcessor::class, $preProcessor->getComponent());
        self::assertSame(['field' => 'a'], $preProcessor->getConfiguration());
        self::assertInstanceOf(LegacyConverterAdapter::class, $task->getConverters()[0]);
        self::assertInstanceOf(LegacyPostProcessorAdapter::class, $task->getPostProcessors()[0]);
        self::assertInstanceOf(LegacyFinisherAdapter::class, $task->getFinishers()[0]);
    }

    public function testComponentListsAreOptional(): void
    {
        $task = $this->subject->create('minimal', ['source' => ['class' => 'test'], 'target' => []]);

        self::assertSame([], $task->getInitializers());
        self::assertSame([], $task->getPreProcessors());
        self::assertSame([], $task->getConverters());
        self::assertSame([], $task->getPostProcessors());
        self::assertSame([], $task->getFinishers());
        self::assertSame([], $task->getFilters());
        self::assertFalse($task->getIdMapSettings()->isEnabled());
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
            'idMap not an array' => [['source' => ['class' => 'test'], 'target' => [], 'idMap' => 'pages'], 1794600021],
            'idMap incomplete' => [['source' => ['class' => 'test'], 'target' => [], 'idMap' => ['namespace' => 'pages']], 1794600002],
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
