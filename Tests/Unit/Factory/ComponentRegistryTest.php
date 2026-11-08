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

use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\Exception\InvalidClassException;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Pipeline;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';
require_once __DIR__ . '/../Service/Fixtures/Components.php';

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $subject;

    protected function setUp(): void
    {
        $this->subject = new ComponentRegistry();
    }

    public function testResolvesRegisteredName(): void
    {
        $this->subject->register(DataSourceInterface::class, 'array', IdentifiableSource::class);

        self::assertSame(IdentifiableSource::class, $this->subject->resolve(DataSourceInterface::class, 'array'));
        self::assertSame(['array' => IdentifiableSource::class], $this->subject->getRegistered(DataSourceInterface::class));
    }

    public function testNamesAreRegisteredPerInterface(): void
    {
        $this->subject->register(DataSourceInterface::class, 'foo', IdentifiableSource::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794230004);

        $this->subject->resolve(PreProcessorInterface::class, 'foo');
    }

    public function testResolvesFullyQualifiedClassName(): void
    {
        self::assertSame(
            SetFieldPreProcessor::class,
            $this->subject->resolve(PreProcessorInterface::class, SetFieldPreProcessor::class)
        );
    }

    public function testResolvesDefaultWithoutName(): void
    {
        $this->subject->setDefault(DataSourceInterface::class, IdentifiableSource::class);

        self::assertSame(IdentifiableSource::class, $this->subject->resolve(DataSourceInterface::class));
        self::assertSame(IdentifiableSource::class, $this->subject->resolve(DataSourceInterface::class, ''));
    }

    public function testThrowsWithoutNameAndDefault(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794230003);

        $this->subject->resolve(DataSourceInterface::class);
    }

    public function testThrowsForClassNotImplementingInterface(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794230005);

        $this->subject->resolve(DataSourceInterface::class, SetFieldPreProcessor::class);
    }

    public function testRegisterRejectsClassNotImplementingInterface(): void
    {
        $this->expectException(InvalidClassException::class);
        $this->expectExceptionCode(1794230006);

        $this->subject->register(DataSourceInterface::class, 'foo', SetFieldPreProcessor::class);
    }

    public function testSetDefaultRejectsClassNotImplementingInterface(): void
    {
        $this->expectException(InvalidClassException::class);

        $this->subject->setDefault(DataSourceInterface::class, SetFieldPreProcessor::class);
    }

    public function testRegisterRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subject->register(DataSourceInterface::class, '', IdentifiableSource::class);
    }

    public function testAcceptsDeprecatedClassForPipelineInterface(): void
    {
        $this->subject->register(Pipeline\PreProcessorInterface::class, 'setField', SetFieldPreProcessor::class);

        self::assertSame(SetFieldPreProcessor::class, $this->subject->resolve(Pipeline\PreProcessorInterface::class, 'setField'));
    }
}
