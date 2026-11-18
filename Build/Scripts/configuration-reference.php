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

/*
 * Writes Documentation/Configuration/Components.md from the class docblocks of
 * the components. With --check, it only tells whether the file is current.
 */

use CPSIT\ImportExportCore\Build\Documentation\ConfigurationReferenceGenerator;

$root = dirname(__DIR__, 2);
require $root . '/.build/vendor/autoload.php';

$target = $root . '/Documentation/Configuration/Components.md';
$check = in_array('--check', $argv, true);

$generator = new ConfigurationReferenceGenerator();
try {
    $content = $generator->generate($generator->findComponents($root . '/Classes', 'CPSIT\\ImportExportCore'));
} catch (UnexpectedValueException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

if ($check) {
    if (!is_file($target) || file_get_contents($target) !== $content) {
        fwrite(STDERR, 'Documentation/Configuration/Components.md is not current. Run `composer docs:reference`.' . PHP_EOL);
        exit(1);
    }
    exit(0);
}

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0777, true);
}
file_put_contents($target, $content);
echo 'Written: Documentation/Configuration/Components.md' . PHP_EOL;
