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
 * Returned by an ID map for a source ID that has no mapping.
 */
final readonly class NullIdMapping extends IdMapping
{
    public function __construct(string $namespace, string $sourceId)
    {
        parent::__construct($namespace, $sourceId, '');
    }

    public function exists(): bool
    {
        return false;
    }
}
