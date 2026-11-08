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

namespace CPSIT\ImportExportCore\Tests\Unit\Pipeline\Legacy;

use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyComponents;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyConverterAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyFinisherAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyInitializerAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyPostProcessorAdapter;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyPreProcessorAdapter;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\AppendingInitializer;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\FieldPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\MarkingPostProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\ObjectConverter;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\RecordingFinisher;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Service/Fixtures/Components.php';

class LegacyComponentsTest extends TestCase
{
    public function testIsCompatible(): void
    {
        self::assertTrue(LegacyComponents::isCompatible(FieldPreProcessor::class, PreProcessorInterface::class));
        self::assertTrue(LegacyComponents::isCompatible(SetFieldPreProcessor::class, PreProcessorInterface::class));
        self::assertFalse(LegacyComponents::isCompatible(SetFieldPreProcessor::class, ConverterInterface::class));
        self::assertFalse(LegacyComponents::isCompatible(SetFieldPreProcessor::class, DataSourceInterface::class));
    }

    public function testDoesNotWrapPipelineComponents(): void
    {
        $component = new FieldPreProcessor();

        self::assertSame($component, LegacyComponents::adapt($component, PreProcessorInterface::class));
    }

    public function testDoesNotWrapComponentOfOtherRole(): void
    {
        $component = new SetFieldPreProcessor();

        self::assertSame($component, LegacyComponents::adapt($component, ConverterInterface::class));
        self::assertSame($component, LegacyComponents::adapt($component, DataSourceInterface::class));
    }

    public function testPreProcessorAdapterReturnsProcessedRecord(): void
    {
        $component = new SetFieldPreProcessor();
        $subject = new LegacyPreProcessorAdapter($component);
        $subject->setConfiguration(['field' => 'title', 'value' => 'foo']);

        self::assertSame(['id' => 1, 'title' => 'foo'], $subject->process(['id' => 1]));
        self::assertSame(['field' => 'title', 'value' => 'foo'], $component->getConfiguration());
        self::assertTrue($subject->isConfigurationValid([]));
        self::assertSame($component, $subject->getComponent());
    }

    public function testDelegatesDisabledCheck(): void
    {
        $component = new SetFieldPreProcessor();
        $subject = new LegacyPreProcessorAdapter($component);

        self::assertFalse($subject->isDisabled([], [], new TaskResult()));
        $component->disabled = true;
        self::assertTrue($subject->isDisabled([], [], new TaskResult()));
    }

    public function testConverterAdapterReturnsConvertedRecord(): void
    {
        $subject = new LegacyConverterAdapter(new ObjectConverter());

        self::assertEquals((object)['id' => 1], $subject->convert(['id' => 1]));
    }

    public function testPostProcessorAdapterReturnsConvertedRecordAndDiscardsRecordChanges(): void
    {
        $subject = new LegacyPostProcessorAdapter(new MarkingPostProcessor());
        $record = ['id' => 1];

        $converted = $subject->process((object)['id' => 1], $record);

        self::assertEquals((object)['id' => 1, 'postProcessed' => true], $converted);
        self::assertSame(['id' => 1], $record);
    }

    public function testInitializerAdapterPassesNoRecords(): void
    {
        $component = new AppendingInitializer();

        (new LegacyInitializerAdapter($component))->initialize();

        self::assertSame(1, $component->calls);
    }

    public function testFinisherAdapterPassesResultAndNoRecords(): void
    {
        $component = new RecordingFinisher();
        $result = new TaskResult();

        (new LegacyFinisherAdapter($component))->finish($result);

        self::assertSame([], $component->records);
        self::assertSame($result, $component->result);
    }
}
