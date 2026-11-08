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

namespace CPSIT\ImportExportCore\Tests\Unit\Domain\Model;

use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\Domain\Model\TransferTaskInterface;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use PHPUnit\Framework\TestCase;

class TransferTaskTest extends TestCase
{
    protected TransferTask $subject;

    protected function setUp(): void
    {
        $this->subject = new TransferTask();
    }

    public function testImplementsInterfaces(): void
    {
        self::assertInstanceOf(TransferTaskInterface::class, $this->subject);
        self::assertInstanceOf(IdentifiableInterface::class, $this->subject);
    }

    public function testHasDefaults(): void
    {
        self::assertNull($this->subject->getIdentifier());
        self::assertSame('', $this->subject->getLabel());
        self::assertSame('', $this->subject->getDescription());
        self::assertSame('', $this->subject->getTargetClass());
        self::assertNull($this->subject->getSource());
        self::assertNull($this->subject->getTarget());
        self::assertSame([], $this->subject->getInitializers());
        self::assertSame([], $this->subject->getPreProcessors());
        self::assertSame([], $this->subject->getConverters());
        self::assertSame([], $this->subject->getPostProcessors());
        self::assertSame([], $this->subject->getFinishers());
    }

    public function testSetsScalarProperties(): void
    {
        $this->subject->setIdentifier('foo');
        $this->subject->setLabel('Foo');
        $this->subject->setDescription('Imports foo');
        $this->subject->setTargetClass(\stdClass::class);

        self::assertSame('foo', $this->subject->getIdentifier());
        self::assertSame('Foo', $this->subject->getLabel());
        self::assertSame('Imports foo', $this->subject->getDescription());
        self::assertSame(\stdClass::class, $this->subject->getTargetClass());
    }

    public function testSetsSourceAndTarget(): void
    {
        $source = self::createStub(DataSourceInterface::class);
        $target = self::createStub(DataTargetInterface::class);

        $this->subject->setSource($source);
        $this->subject->setTarget($target);

        self::assertSame($source, $this->subject->getSource());
        self::assertSame($target, $this->subject->getTarget());
    }

    public function testSetsComponents(): void
    {
        $initializers = [self::createStub(InitializerInterface::class)];
        $preProcessors = [self::createStub(PreProcessorInterface::class)];
        $converters = [self::createStub(ConverterInterface::class)];
        $postProcessors = [self::createStub(PostProcessorInterface::class)];
        $finishers = [self::createStub(FinisherInterface::class)];

        $this->subject->setInitializers($initializers);
        $this->subject->setPreProcessors($preProcessors);
        $this->subject->setConverters($converters);
        $this->subject->setPostProcessors($postProcessors);
        $this->subject->setFinishers($finishers);

        self::assertSame($initializers, $this->subject->getInitializers());
        self::assertSame($preProcessors, $this->subject->getPreProcessors());
        self::assertSame($converters, $this->subject->getConverters());
        self::assertSame($postProcessors, $this->subject->getPostProcessors());
        self::assertSame($finishers, $this->subject->getFinishers());
    }
}
