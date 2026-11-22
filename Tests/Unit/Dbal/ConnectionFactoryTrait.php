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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Creates SQLite connections of the class returned by connectionClass().
 * Test cases overriding it run against another Connection subclass.
 */
trait ConnectionFactoryTrait
{
    /**
     * @return class-string<Connection>
     */
    protected static function connectionClass(): string
    {
        return Connection::class;
    }

    protected static function createConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => static::connectionClass(),
        ]);
    }
}
