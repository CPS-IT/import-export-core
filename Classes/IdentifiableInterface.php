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

namespace CPSIT\ImportExportCore;

/**
 * Interface IdentifiableInterface
 */
interface IdentifiableInterface
{
    /**
     * Sets the identifier
     *
     * @param string $identifier
     * @return mixed
     */
    public function setIdentifier(string $identifier);

    /**
     * Gets the identifier
     *
     * @return string|null
     */
    public function getIdentifier(): ?string;
}
