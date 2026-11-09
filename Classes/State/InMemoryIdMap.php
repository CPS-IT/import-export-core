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
 * Keeps the ID map in memory, e.g. for tests or transfers within one process.
 */
final class InMemoryIdMap implements IdMapInterface
{
    /**
     * @var array<string, array<string, IdMapping>> namespace => source ID => mapping
     */
    private array $mappings = [];

    public function set(IdMapping $mapping): void
    {
        $this->mappings[$mapping->namespace][$mapping->sourceId] = $mapping;
    }

    public function get(string $namespace, string $sourceId): IdMapping
    {
        return $this->mappings[$namespace][$sourceId] ?? new NullIdMapping($namespace, $sourceId);
    }

    public function has(string $namespace, string $sourceId): bool
    {
        return isset($this->mappings[$namespace][$sourceId]);
    }

    public function all(string $namespace): iterable
    {
        return array_values($this->mappings[$namespace] ?? []);
    }
}
