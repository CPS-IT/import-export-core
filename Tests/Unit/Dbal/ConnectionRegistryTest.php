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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal;

use CPSIT\ImportExportCore\Dbal\ConnectionRegistry;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class ConnectionRegistryTest extends TestCase
{
    public function testReturnsConnectionsByName(): void
    {
        $source = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $target = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $subject = new ConnectionRegistry(['source' => $source]);
        $subject->add('target', $target);

        self::assertTrue($subject->hasConnection('source'));
        self::assertTrue($subject->hasConnection('target'));
        self::assertFalse($subject->hasConnection('default'));
        self::assertSame($source, $subject->getConnection('source'));
        self::assertSame($target, $subject->getConnection('target'));
    }

    public function testThrowsForUnknownConnection(): void
    {
        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(1794700001);

        (new ConnectionRegistry())->getConnection('default');
    }
}
