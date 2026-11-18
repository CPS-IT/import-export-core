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

namespace CPSIT\ImportExportCore\Tests\Unit\Build\Documentation;

use CPSIT\ImportExportCore\Build\Documentation\DocblockParser;
use PHPUnit\Framework\TestCase;

class DocblockParserTest extends TestCase
{
    private DocblockParser $subject;

    protected function setUp(): void
    {
        $this->subject = new DocblockParser();
    }

    public function testReadsSummaryAndConfiguration(): void
    {
        $docComment = <<<'DOC'
            /**
             * Reads records.
             *
             * Second paragraph, see {@see Filter}.
             *
             * Configuration:
             *
             * - `table` (string, required): the table
             *   in the database
             *   - nested
             *
             * @phpstan-type Foo array{}
             */
            DOC;

        self::assertSame(
            [
                'summary' => "Reads records.\n\nSecond paragraph, see `Filter`.",
                'configuration' => "- `table` (string, required): the table\n  in the database\n  - nested",
            ],
            $this->subject->parse($docComment)
        );
    }

    public function testReadsConfigurationOnTheMarkerLine(): void
    {
        self::assertSame(
            ['summary' => 'Reads nothing.', 'configuration' => 'none.'],
            $this->subject->parse("/**\n * Reads nothing.\n *\n * Configuration: none.\n */")
        );
    }

    public function testReturnsNullWithoutConfigurationSection(): void
    {
        self::assertSame(
            ['summary' => 'Reads records.', 'configuration' => null],
            $this->subject->parse("/**\n * Reads records.\n *\n * @internal\n */")
        );
    }

    public function testReadsSingleLineDocblock(): void
    {
        self::assertSame(
            ['summary' => 'Reads records.', 'configuration' => null],
            $this->subject->parse('/** Reads records. */')
        );
    }

    public function testReadsEmptyDocblock(): void
    {
        self::assertSame(['summary' => '', 'configuration' => null], $this->subject->parse(''));
    }
}
