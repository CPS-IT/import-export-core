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

use CPSIT\ImportExportCore\Component\ComponentInterface;

/**
 * Interface DataTargetInterface
 */
interface DataTargetInterface extends ComponentInterface
{
    /**
     * Persist both new and updated records.
     *
     * @param array|object $object Record to persist. Either an array or an object, depending on the target.
     * @param array $configuration Configuration array.
     * @return mixed
     */
    public function persist($object, ?array $configuration = null);

    /**
     * Persists all record or objects
     *
     * @param array|null $result
     * @param array|null $configuration
     * @return mixed
     */
    public function persistAll($result = null, ?array $configuration = null);
}
