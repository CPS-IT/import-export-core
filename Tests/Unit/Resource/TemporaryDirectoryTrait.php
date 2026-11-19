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

namespace CPSIT\ImportExportCore\Tests\Unit\Resource;

/**
 * Creates a directory below the system's temporary directory and removes it
 * after the test.
 */
trait TemporaryDirectoryTrait
{
    private string $temporaryDirectory = '';

    protected function createTemporaryDirectory(): string
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/import-export-core-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory);
        $this->temporaryDirectory = (string)realpath($this->temporaryDirectory);

        return $this->temporaryDirectory;
    }

    protected function removeTemporaryDirectory(): void
    {
        if ($this->temporaryDirectory === '' || !is_dir($this->temporaryDirectory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->temporaryDirectory);
        $this->temporaryDirectory = '';
    }
}
