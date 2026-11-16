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

use CPSIT\ImportExportCore\Processor\FieldList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FieldListTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, list<string>|null}>
     */
    public static function parseProvider(): iterable
    {
        yield 'comma-separated string' => ['a, b ,c', ['a', 'b', 'c']];
        yield 'empty names are dropped' => ['a,,b,', ['a', 'b']];
        yield 'list' => [[' a', 'b '], ['a', 'b']];
        yield 'empty string' => ['', null];
        yield 'only commas' => [' , ', null];
        yield 'empty list' => [[], null];
        yield 'map' => [['a' => 'b'], null];
        yield 'list with non-string' => [['a', 1], null];
        yield 'integer' => [1, null];
        yield 'null' => [null, null];
    }

    /**
     * @param list<string>|null $expected
     */
    #[DataProvider('parseProvider')]
    public function testParse(mixed $value, ?array $expected): void
    {
        self::assertSame($expected, FieldList::parse($value));
    }
}
