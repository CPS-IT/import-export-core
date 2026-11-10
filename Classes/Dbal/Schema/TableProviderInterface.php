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

namespace CPSIT\ImportExportCore\Dbal\Schema;

use Doctrine\DBAL\Schema\Table;

/**
 * Implemented by components that store data in their own tables. The tables
 * are created by the `SchemaInstaller`, or by other schema tools that take the
 * table definitions (e.g. a framework's database migrations).
 */
interface TableProviderInterface
{
    /**
     * @return list<Table>
     */
    public function getTables(): array;
}
