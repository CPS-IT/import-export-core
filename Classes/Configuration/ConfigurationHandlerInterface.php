<?php declare(strict_types=1);

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

namespace CPSIT\ImportExportCore\Configuration;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;

/**
 * Manages configuration from multiple sources.
 */
interface ConfigurationHandlerInterface
{
    /**
     * Add configuration from a loader
     *
     * The new configuration is merged into the existing one: associative
     * arrays are merged recursively, scalar values and lists of the new
     * configuration replace existing ones.
     *
     * @param ConfigurationLoaderInterface $loader
     * @param string $path Path to configuration file
     * @throws FileNotFoundException
     * @throws ParseException
     * @return ConfigurationHandlerInterface
     */
    public function addConfiguration(ConfigurationLoaderInterface $loader, string $path): ConfigurationHandlerInterface;

    /**
     * Get import/export tasks
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTasks(): array;

    /**
     * Get import/export sets
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSets(): array;

    /**
     * Get the full configuration array
     *
     * @return array<string, mixed>
     */
    public function getFullConfiguration(): array;
}
