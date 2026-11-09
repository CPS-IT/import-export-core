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

use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface as LegacyInitializerInterface;
use CPSIT\ImportExportCore\Pipeline\InitializerInterface;
use CPSIT\ImportExportCore\Pipeline\TaskContext;

/**
 * The adapted initializer receives an empty list of records. The context is
 * not passed on.
 *
 * @internal
 * @extends AbstractLegacyAdapter<LegacyInitializerInterface>
 */
final class LegacyInitializerAdapter extends AbstractLegacyAdapter implements InitializerInterface
{
    public function __construct(LegacyInitializerInterface $component)
    {
        parent::__construct($component);
    }

    public function initialize(TaskContext $context): void
    {
        $records = [];
        $this->component->process($this->component->getConfiguration(), $records);
    }
}
