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

use CPSIT\ImportExportCore\IdentifiableTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IdentifiableTraitTest extends TestCase
{
    /**
     * @var object Class using IdentifiableTrait
     */
    protected $subject;

    protected function setUp(): void
    {
        // In PHPUnit 12, getObjectForTrait is removed
        // Create an anonymous class that uses the trait instead
        $this->subject = new class() {
            use IdentifiableTrait;
        };
    }

    #[Test]
    public function testGetIdentifierInitiallyReturnsNull(): void
    {
        $this->assertNull(
            $this->subject->getIdentifier()
        );
    }

    #[Test]
    public function testSetIdentifierSetsIdentifier(): void
    {
        $identifier = 'foo';
        $this->subject->setIdentifier($identifier);
        $this->assertSame(
            $identifier,
            $this->subject->getIdentifier()
        );
    }
}
