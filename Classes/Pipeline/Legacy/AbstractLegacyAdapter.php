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

namespace CPSIT\ImportExportCore\Pipeline\Legacy;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\DisableableInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;

/**
 * Delegates configuration and the `disabled` check to the adapted component.
 * The adapted component receives its configuration with each call, as before.
 *
 * @internal
 * @template T of InitializerInterface|PreProcessorInterface|ConverterInterface|PostProcessorInterface|FinisherInterface
 */
abstract class AbstractLegacyAdapter implements LegacyAdapterInterface, ConfigurableInterface, DisableableInterface
{
    /**
     * @param T $component
     */
    public function __construct(protected readonly ComponentInterface $component)
    {
    }

    /**
     * @return T
     */
    public function getComponent(): ComponentInterface
    {
        return $this->component;
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return $this->component->isConfigurationValid($configuration);
    }

    public function getConfiguration(): array
    {
        return $this->component->getConfiguration();
    }

    public function setConfiguration(array $configuration): void
    {
        $this->component->setConfiguration($configuration);
    }

    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool
    {
        return $this->component->isDisabled($configuration, $record, $result);
    }
}
