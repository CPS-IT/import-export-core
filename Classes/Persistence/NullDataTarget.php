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
 * Data target that does not write anything, e.g. for tasks whose finishers
 * use the result, or to try a configuration.
 *
 * Configuration: none.
 */
final class NullDataTarget implements DataTargetInterface
{
    public function persist(array|object $object, ?array $configuration = null): bool
    {
        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): void
    {
    }
}
