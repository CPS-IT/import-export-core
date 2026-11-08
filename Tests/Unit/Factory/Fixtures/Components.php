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

namespace CPSIT\ImportExportCore\Tests\Unit\Factory\Fixtures;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\IdentifiableTrait;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;

/**
 * Data source without constructor arguments, for factory tests.
 */
final class IdentifiableSource implements DataSourceInterface, ConfigurableInterface, IdentifiableInterface
{
    use ConfigurableTrait;
    use IdentifiableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        return true;
    }

    public function getRecords(array $configuration): iterable
    {
        return [];
    }
}

/**
 * Data source that requires the option "table".
 */
final class StrictSource implements DataSourceInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $configuration = [];

    /**
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        if (!isset($configuration['table'])) {
            throw new InvalidConfigurationException('Missing option table.', 1);
        }
        $this->configuration = $configuration;
    }

    public function getRecords(array $configuration): iterable
    {
        return [];
    }
}

/**
 * Component without configuration.
 */
final class PlainComponent implements ComponentInterface
{
}
