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

use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface as LegacyPostProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PostProcessorInterface;

/**
 * Changes of the adapted post-processor to the record before conversion are
 * discarded.
 *
 * @internal
 * @extends AbstractLegacyAdapter<LegacyPostProcessorInterface>
 */
final class LegacyPostProcessorAdapter extends AbstractLegacyAdapter implements PostProcessorInterface
{
    public function __construct(LegacyPostProcessorInterface $component)
    {
        parent::__construct($component);
    }

    public function process(mixed $convertedRecord, array $record): mixed
    {
        $this->component->process($this->component->getConfiguration(), $convertedRecord, $record);

        return $convertedRecord;
    }
}
