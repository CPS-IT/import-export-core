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

namespace CPSIT\ImportExportCore\Tests\Unit\State;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\IdMapSettings;
use PHPUnit\Framework\TestCase;

class IdMapSettingsTest extends TestCase
{
    public function testIsDisabledByDefault(): void
    {
        self::assertFalse((new IdMapSettings())->isEnabled());
    }

    public function testCreatesFromArray(): void
    {
        $subject = IdMapSettings::fromArray(['namespace' => 'pages', 'sourceIdField' => 'uid']);

        self::assertTrue($subject->isEnabled());
        self::assertSame('pages', $subject->namespace);
        self::assertSame('uid', $subject->sourceIdField);
    }

    public function testFromArrayRequiresBothValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(1794600002);

        IdMapSettings::fromArray(['namespace' => 'pages']);
    }

    public function testRejectsHalfConfiguredSettings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794600001);

        new IdMapSettings('pages');
    }

    public function testChecksumDependsOnContentAndOrder(): void
    {
        self::assertSame(IdMapping::checksum(['a' => 1, 'b' => 'ä/']), IdMapping::checksum(['a' => 1, 'b' => 'ä/']));
        self::assertNotSame(IdMapping::checksum(['a' => 1]), IdMapping::checksum(['a' => 2]));
        self::assertNotSame(IdMapping::checksum(['a' => 1, 'b' => 2]), IdMapping::checksum(['b' => 2, 'a' => 1]));
    }
}
