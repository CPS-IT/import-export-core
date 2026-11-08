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

namespace CPSIT\ImportExportCore\Factory;

/**
 * Creates objects of a given class.
 *
 * Implementations must return an independent instance on each call: the
 * factories configure the returned object, so a shared instance would carry
 * the configuration of one task into another.
 */
interface InstantiatorInterface
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function instantiate(string $className): object;
}
