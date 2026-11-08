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

namespace CPSIT\ImportExportCore\Tests\Unit\Exception;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ImportExportExceptionInterface;
use CPSIT\ImportExportCore\Exception\InvalidArgumentException;
use CPSIT\ImportExportCore\Exception\InvalidClassException;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\Exception\MissingClassException;
use CPSIT\ImportExportCore\Exception\MissingInterfaceException;
use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Exception\PersistenceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function exceptionDataProvider(): array
    {
        return [
            'FileNotFoundException' => [FileNotFoundException::class, \RuntimeException::class],
            'InvalidArgumentException' => [InvalidArgumentException::class, \InvalidArgumentException::class],
            'InvalidClassException' => [InvalidClassException::class, \RuntimeException::class],
            'InvalidConfigurationException' => [InvalidConfigurationException::class, \RuntimeException::class],
            'MissingClassException' => [MissingClassException::class, \RuntimeException::class],
            'MissingInterfaceException' => [MissingInterfaceException::class, \RuntimeException::class],
            'MissingResourceException' => [MissingResourceException::class, \RuntimeException::class],
            'ParseException' => [ParseException::class, \RuntimeException::class],
            'PersistenceException' => [PersistenceException::class, \RuntimeException::class],
        ];
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     * @param class-string<\Throwable> $parentClass
     */
    #[Test]
    #[DataProvider('exceptionDataProvider')]
    public function exceptionImplementsPackageInterfaceAndExtendsSplException(
        string $exceptionClass,
        string $parentClass
    ): void {
        $exception = new $exceptionClass('message');

        $this->assertInstanceOf(ImportExportExceptionInterface::class, $exception);
        $this->assertInstanceOf($parentClass, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
