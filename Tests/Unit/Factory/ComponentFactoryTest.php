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

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Factory\ComponentFactory;
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Factory\InstantiatorInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\IdentifiableSource;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\PlainComponent;
use CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures\StrictSource;
use CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures\SetFieldPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/Components.php';
require_once __DIR__ . '/../Service/Fixtures/Components.php';

class ComponentFactoryTest extends TestCase
{
    private ComponentRegistry $registry;
    private ComponentFactory $subject;

    protected function setUp(): void
    {
        $this->registry = new ComponentRegistry();
        $this->subject = new ComponentFactory($this->registry);
    }

    public function testCreatesComponentByShortName(): void
    {
        $this->registry->register(PreProcessorInterface::class, 'setField', SetFieldPreProcessor::class);

        $component = $this->subject->create(PreProcessorInterface::class, ['class' => 'setField', 'config' => ['field' => 'a']]);

        self::assertInstanceOf(SetFieldPreProcessor::class, $component);
        self::assertSame(['field' => 'a'], $component->getConfiguration());
    }

    public function testCreatesComponentByClassName(): void
    {
        $component = $this->subject->create(PreProcessorInterface::class, ['class' => SetFieldPreProcessor::class]);

        self::assertInstanceOf(SetFieldPreProcessor::class, $component);
    }

    public function testUsesDefaultClass(): void
    {
        $this->registry->setDefault(DataSourceInterface::class, IdentifiableSource::class);

        self::assertInstanceOf(IdentifiableSource::class, $this->subject->create(DataSourceInterface::class, []));
    }

    public function testCreatesIndependentInstances(): void
    {
        $settings = ['class' => SetFieldPreProcessor::class];

        self::assertNotSame(
            $this->subject->create(PreProcessorInterface::class, $settings),
            $this->subject->create(PreProcessorInterface::class, $settings)
        );
    }

    public function testSetsIdentifier(): void
    {
        $source = $this->subject->create(DataSourceInterface::class, ['class' => IdentifiableSource::class, 'identifier' => 'legacy']);

        self::assertInstanceOf(IdentifiableSource::class, $source);
        self::assertSame('legacy', $source->getIdentifier());
    }

    public function testSetsEmptyConfigurationByDefault(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing option table.');

        $this->subject->create(DataSourceInterface::class, ['class' => StrictSource::class]);
    }

    public function testSetsConfigurationOnComponentsWithoutConfigurableInterface(): void
    {
        $source = $this->subject->create(DataSourceInterface::class, ['class' => StrictSource::class, 'config' => ['table' => 'tx_foo']]);

        self::assertInstanceOf(StrictSource::class, $source);
        self::assertSame(['table' => 'tx_foo'], $source->configuration);
    }

    public function testCreatesComponentWithoutConfiguration(): void
    {
        self::assertInstanceOf(
            PlainComponent::class,
            $this->subject->create(ComponentInterface::class, ['class' => PlainComponent::class])
        );
    }

    public function testUsesInstantiator(): void
    {
        $instance = new SetFieldPreProcessor();
        $instantiator = $this->createMock(InstantiatorInterface::class);
        $instantiator->expects(self::once())->method('instantiate')->with(SetFieldPreProcessor::class)->willReturn($instance);
        $subject = new ComponentFactory($this->registry, $instantiator);

        self::assertSame($instance, $subject->create(PreProcessorInterface::class, ['class' => SetFieldPreProcessor::class]));
    }

    public function testAddsTaskToResolveError(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794230004);
        $this->expectExceptionMessage('Task: import-news.');

        $this->subject->create(PreProcessorInterface::class, ['class' => 'unknown'], 'import-news');
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function invalidSettingsProvider(): array
    {
        return [
            'class not a string' => [['class' => 1], 1794230011],
            'config not an array' => [['class' => IdentifiableSource::class, 'config' => 'foo'], 1794230012],
            'identifier not a string' => [['class' => IdentifiableSource::class, 'identifier' => 1], 1794230013],
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
        $this->expectExceptionMessage('in task "foo"');

        $this->subject->create(DataSourceInterface::class, $settings, 'foo');
    }
}
