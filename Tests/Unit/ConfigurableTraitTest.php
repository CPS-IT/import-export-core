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

namespace CPSIT\ImportExportCore\Tests\Unit;

use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigurableTraitTest extends TestCase
{
    protected object $subject;

    protected function setUp(): void
    {
        $this->subject = new class() {
            use ConfigurableTrait;

            public function isConfigurationValid(array $configuration): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function testGetConfigurationInitiallyReturnsEmptyArray(): void
    {
        $expected = [];
        $this->assertSame(
            $expected,
            $this->subject->getConfiguration()
        );
    }

    #[Test]
    public function testSetConfigurationSetsValidConfiguration(): void
    {
        $configuration = ['foo'];

        $this->subject->setConfiguration($configuration);

        $this->assertSame(
            $configuration,
            $this->subject->getConfiguration()
        );
    }

    #[Test]
    public function testSetConfigurationThrowsExceptionForInvalidConfiguration(): void
    {
        // set up a test subject which validates any configuration as false
        $this->subject = new class() {
            use ConfigurableTrait;

            public function isConfigurationValid(array $configuration): bool
            {
                return false;
            }
        };

        $this->expectExceptionCode(1_451_659_793);
        $this->expectException(InvalidConfigurationException::class);
        $configuration = ['foo'];

        $this->subject->setConfiguration($configuration);
    }
}
