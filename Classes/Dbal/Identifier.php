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

use Doctrine\DBAL\Connection;

/**
 * Checks and quotes table and column names taken from configuration or from
 * record keys. Only letters, digits and underscores are allowed; table names
 * may be qualified by a schema (`schema.table`).
 *
 * @internal
 */
final class Identifier
{
    private const string NAME = '[A-Za-z_][A-Za-z0-9_]*';

    public static function isColumn(mixed $name): bool
    {
        return \is_string($name) && preg_match('/^' . self::NAME . '$/', $name) === 1;
    }

    public static function isTable(mixed $name): bool
    {
        return \is_string($name) && preg_match('/^(' . self::NAME . '\.)?' . self::NAME . '$/', $name) === 1;
    }

    public static function quote(Connection $connection, string $name): string
    {
        return implode(
            '.',
            array_map($connection->quoteSingleIdentifier(...), explode('.', $name))
        );
    }
}
