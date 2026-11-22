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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\QuotingConnection;

use CPSIT\ImportExportCore\Tests\Unit\Dbal\DataSourceDBALTest;
use CPSIT\ImportExportCore\Tests\Unit\Dbal\Fixtures\QuotingConnection;

/**
 * Runs the tests of the parent class on a connection that quotes names itself.
 */
final class DataSourceDBALOnQuotingConnectionTest extends DataSourceDBALTest
{
    protected static function connectionClass(): string
    {
        return QuotingConnection::class;
    }
}
