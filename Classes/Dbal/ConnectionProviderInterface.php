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

namespace CPSIT\ImportExportCore\Dbal;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use Doctrine\DBAL\Connection;

/**
 * Provides database connections by name. The DBAL source and target name the
 * connection in their configuration (`connection`, default `default`), so a
 * task can read from one database and write to another.
 */
interface ConnectionProviderInterface
{
    public const string DEFAULT_CONNECTION = 'default';

    public function hasConnection(string $name): bool;

    /**
     * @throws MissingResourceException if there is no connection with this name
     */
    public function getConnection(string $name): Connection;
}
