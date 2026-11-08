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

use CPSIT\ImportExportCore\Component\Converter\ConverterInterface as LegacyConverterInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;

/**
 * @internal
 * @extends AbstractLegacyAdapter<LegacyConverterInterface>
 */
final class LegacyConverterAdapter extends AbstractLegacyAdapter implements ConverterInterface
{
    public function __construct(LegacyConverterInterface $component)
    {
        parent::__construct($component);
    }

    public function convert(array $record): mixed
    {
        return $this->component->convert($record, $this->component->getConfiguration());
    }
}
