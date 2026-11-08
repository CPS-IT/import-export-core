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

namespace CPSIT\ImportExportCore\Queue;

/**
 * Values match the status column of the extension's queue table.
 */
enum QueueItemStatus: int
{
    case New = 0;
    case Processing = 1;
    case Finished = 2;
    case Failed = 3;
}
