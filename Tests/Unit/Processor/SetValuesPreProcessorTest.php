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

namespace CPSIT\ImportExportCore\Tests\Unit\Processor;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Processor\SetValuesPreProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SetValuesPreProcessorTest extends TestCase
{
    public function testSetsAndReplacesValues(): void
    {
        $subject = new SetValuesPreProcessor();
        $subject->setConfiguration(['values' => ['type' => 'Course', 'pid' => 12, 'note' => null]]);

        self::assertSame(
            ['uid' => 1, 'type' => 'Course', 'pid' => 12, 'note' => null],
            $subject->process(['uid' => 1, 'type' => 'Old'])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['values' => []]];
        yield 'list' => [['values' => ['a']]];
        yield 'array value' => [['values' => ['a' => [1]]]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new SetValuesPreProcessor())->setConfiguration($configuration);
    }
}
