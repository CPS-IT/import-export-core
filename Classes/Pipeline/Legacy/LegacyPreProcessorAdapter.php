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

namespace CPSIT\ImportExportCore\Pipeline\Legacy;

use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface as LegacyPreProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * @internal
 * @extends AbstractLegacyAdapter<LegacyPreProcessorInterface>
 */
final class LegacyPreProcessorAdapter extends AbstractLegacyAdapter implements PreProcessorInterface
{
    public function __construct(LegacyPreProcessorInterface $component)
    {
        parent::__construct($component);
    }

    public function process(array $record): array
    {
        $this->component->process($this->component->getConfiguration(), $record);

        return $record;
    }
}
