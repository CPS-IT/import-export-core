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

use CPSIT\ImportExportCore\Factory\ContainerInstantiator;
use CPSIT\ImportExportCore\Factory\Instantiator;
use CPSIT\ImportExportCore\Factory\InstantiatorInterface;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\PlainComponent;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/Fixtures/Components.php';

class ContainerInstantiatorTest extends TestCase
{
    public function testInstantiatorCreatesNewInstances(): void
    {
        $subject = new Instantiator();

        $first = $subject->instantiate(PlainComponent::class);

        self::assertInstanceOf(PlainComponent::class, $first);
        self::assertNotSame($first, $subject->instantiate(PlainComponent::class));
    }

    public function testReturnsCloneOfContainerEntry(): void
    {
        $service = new IdentifiableSource();
        $service->setIdentifier('shared');
        $container = self::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($service);
        $subject = new ContainerInstantiator($container);

        $instance = $subject->instantiate(IdentifiableSource::class);

        self::assertNotSame($service, $instance);
        self::assertSame('shared', $instance->getIdentifier());
    }

    public function testUsesFallbackForUnknownClass(): void
    {
        $container = self::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $fallbackInstance = new PlainComponent();
        $fallback = $this->createMock(InstantiatorInterface::class);
        $fallback->expects(self::once())
            ->method('instantiate')
            ->with(PlainComponent::class)
            ->willReturn($fallbackInstance);
        $subject = new ContainerInstantiator($container, $fallback);

        self::assertSame($fallbackInstance, $subject->instantiate(PlainComponent::class));
    }

    public function testDefaultFallbackCreatesInstance(): void
    {
        $container = self::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        self::assertInstanceOf(PlainComponent::class, (new ContainerInstantiator($container))->instantiate(PlainComponent::class));
    }

    public function testThrowsIfContainerEntryHasWrongType(): void
    {
        $container = self::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new PlainComponent());
        $subject = new ContainerInstantiator($container);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1794230001);

        $subject->instantiate(IdentifiableSource::class);
    }
}
