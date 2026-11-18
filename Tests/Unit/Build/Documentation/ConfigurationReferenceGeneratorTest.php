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

use CPSIT\ImportExportCore\Build\Documentation\ConfigurationReferenceGenerator;
use CPSIT\ImportExportCore\Dbal\DataSourceDBAL;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyPreProcessorAdapter;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\AbstractPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\InvalidPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\NoComponent;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\Sub\SourceWithoutOptions;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\UndocumentedPreProcessor;
use CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\ValidPreProcessor;
use PHPUnit\Framework\TestCase;

class ConfigurationReferenceGeneratorTest extends TestCase
{
    private ConfigurationReferenceGenerator $subject;

    protected function setUp(): void
    {
        $this->subject = new ConfigurationReferenceGenerator();
    }

    public function testFindsInstantiableComponents(): void
    {
        self::assertSame(
            [
                InvalidPreProcessor::class,
                SourceWithoutOptions::class,
                UndocumentedPreProcessor::class,
                ValidPreProcessor::class,
            ],
            $this->subject->findComponents(__DIR__ . '/Fixtures', 'CPSIT\\ImportExportCore\\Tests\\Unit\\Build\\Documentation\\Fixtures')
        );
    }

    public function testIsComponent(): void
    {
        self::assertTrue($this->subject->isComponent(DataSourceDBAL::class));
        self::assertFalse($this->subject->isComponent(AbstractPreProcessor::class));
        self::assertFalse($this->subject->isComponent(NoComponent::class));
        self::assertFalse($this->subject->isComponent(LegacyPreProcessorAdapter::class));
    }

    public function testGeneratesSectionsInOrderOfProcessing(): void
    {
        $reference = $this->subject->generate([ValidPreProcessor::class, NoComponent::class, SourceWithoutOptions::class]);

        self::assertStringStartsWith("# Configuration of components\n", $reference);
        self::assertStringContainsString(
            <<<'MD'
                ## Data sources

                Key in the task: `source`

                ### SourceWithoutOptions

                `CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\Sub\SourceWithoutOptions`

                Reads nothing.

                Configuration:

                none.

                ## Pre-processors

                Key in the task: `preProcessors`

                ### ValidPreProcessor

                `CPSIT\ImportExportCore\Tests\Unit\Build\Documentation\Fixtures\ValidPreProcessor`

                Sets a value.

                Configuration:

                - `field` (string, required): the field
                  to set

                MD,
            $reference
        );
        self::assertStringNotContainsString('NoComponent', $reference);
    }

    public function testThrowsExceptionForInvalidDocblocks(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1795017601);
        $this->expectExceptionMessage(
            InvalidPreProcessor::class . ': invalid line "- `field`: the field (required)"' . \PHP_EOL
            . UndocumentedPreProcessor::class . ': no configuration section'
        );

        $this->subject->generate([InvalidPreProcessor::class, ValidPreProcessor::class, UndocumentedPreProcessor::class]);
    }
}
