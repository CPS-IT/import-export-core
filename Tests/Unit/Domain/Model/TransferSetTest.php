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

namespace CPSIT\ImportExportCore\Tests\Unit\Domain\Model;

use CPSIT\ImportExportCore\Domain\Model\TransferSet;
use CPSIT\ImportExportCore\Domain\Model\TransferSetInterface;
use CPSIT\ImportExportCore\Domain\Model\TransferTask;
use CPSIT\ImportExportCore\IdentifiableInterface;
use PHPUnit\Framework\TestCase;

class TransferSetTest extends TestCase
{
    protected TransferSet $subject;

    protected function setUp(): void
    {
        $this->subject = new TransferSet();
    }

    public function testImplementsInterfaces(): void
    {
        self::assertInstanceOf(TransferSetInterface::class, $this->subject);
        self::assertInstanceOf(IdentifiableInterface::class, $this->subject);
    }

    public function testHasDefaults(): void
    {
        self::assertNull($this->subject->getIdentifier());
        self::assertSame('', $this->subject->getLabel());
        self::assertSame('', $this->subject->getDescription());
        self::assertSame([], $this->subject->getTasks());
    }

    public function testSetsProperties(): void
    {
        $tasks = [new TransferTask(), new TransferTask()];

        $this->subject->setIdentifier('bar');
        $this->subject->setLabel('Bar');
        $this->subject->setDescription('Imports bar');
        $this->subject->setTasks($tasks);

        self::assertSame('bar', $this->subject->getIdentifier());
        self::assertSame('Bar', $this->subject->getLabel());
        self::assertSame('Imports bar', $this->subject->getDescription());
        self::assertSame($tasks, $this->subject->getTasks());
    }
}
