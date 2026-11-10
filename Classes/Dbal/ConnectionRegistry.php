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
 * Holds connections created elsewhere, e.g. by `DriverManager`.
 */
final class ConnectionRegistry implements ConnectionProviderInterface
{
    /**
     * @param array<string, Connection> $connections connections by name
     */
    public function __construct(private array $connections = [])
    {
    }

    public function add(string $name, Connection $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    public function getConnection(string $name): Connection
    {
        if (!isset($this->connections[$name])) {
            throw new MissingResourceException(\sprintf('There is no database connection "%s".', $name), 1794700001);
        }

        return $this->connections[$name];
    }
}
