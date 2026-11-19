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

namespace CPSIT\ImportExportCore\Persistence;

/**
 * Data source without records, e.g. for tasks that only run initializers or
 * finishers. Note that finishers run, but the target's `persistAll()` is not
 * called without records.
 *
 * Configuration: none.
 */
final class NullDataSource implements DataSourceInterface
{
    /**
     * @return array{}
     */
    public function getRecords(array $configuration): iterable
    {
        return [];
    }
}
