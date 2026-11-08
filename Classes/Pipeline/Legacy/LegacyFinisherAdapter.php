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

use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface as LegacyFinisherInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;

/**
 * The adapted finisher receives an empty list of records. Replacing the
 * result has no effect.
 *
 * @internal
 * @extends AbstractLegacyAdapter<LegacyFinisherInterface>
 */
final class LegacyFinisherAdapter extends AbstractLegacyAdapter implements FinisherInterface
{
    public function __construct(LegacyFinisherInterface $component)
    {
        parent::__construct($component);
    }

    public function finish(ResultInterface $result): void
    {
        $records = [];
        $this->component->process($this->component->getConfiguration(), $records, $result);
    }
}
