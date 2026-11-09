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

namespace CPSIT\ImportExportCore\State;

/**
 * Stores nothing. Default of the processor if no ID map is given.
 */
final class NullIdMap implements IdMapInterface
{
    public function set(IdMapping $mapping): void
    {
    }

    public function get(string $namespace, string $sourceId): IdMapping
    {
        return new NullIdMapping($namespace, $sourceId);
    }

    public function has(string $namespace, string $sourceId): bool
    {
        return false;
    }

    public function all(string $namespace): iterable
    {
        return [];
    }
}
