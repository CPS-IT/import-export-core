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
 * Maps source IDs to target IDs across tasks and runs, per namespace (e.g. a
 * table or entity type).
 */
interface IdMapInterface
{
    /**
     * Adds a mapping or replaces the mapping of the same source ID.
     */
    public function set(IdMapping $mapping): void;

    /**
     * Returns the mapping of the source ID, a `NullIdMapping` if there is none.
     */
    public function get(string $namespace, string $sourceId): IdMapping;

    public function has(string $namespace, string $sourceId): bool;

    /**
     * @return iterable<IdMapping> all mappings of the namespace
     */
    public function all(string $namespace): iterable;
}
