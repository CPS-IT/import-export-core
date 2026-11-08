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

namespace CPSIT\ImportExportCore\Tests\Unit\Configuration;

use CPSIT\ImportExportCore\Configuration\ConfigurationHandler;
use CPSIT\ImportExportCore\Configuration\ConfigurationLoaderInterface;
use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigurationHandlerTest extends TestCase
{
    protected ConfigurationHandler $subject;
    protected ConfigurationLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->subject = new ConfigurationHandler();
        $this->loader = $this->createMock(ConfigurationLoaderInterface::class);
    }

    public function testAddConfigurationCallsLoaderWithGivenPath(): void
    {
        $path = '/path/to/config.yaml';
        $config = [
            'import' => [
                'tasks' => [
                    'test' => ['label' => 'Test Task']
                ]
            ]
        ];

        $this->loader->expects($this->once())
            ->method('load')
            ->with($path)
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);
    }

    public function testAddConfigurationThrowsExceptionWhenLoaderThrowsFileNotFoundException(): void
    {
        $path = '/path/to/config.yaml';

        $this->loader->expects($this->once())
            ->method('load')
            ->with($path)
            ->willThrowException(new FileNotFoundException('File not found'));

        $this->expectException(FileNotFoundException::class);
        $this->subject->addConfiguration($this->loader, $path);
    }

    public function testAddConfigurationThrowsExceptionWhenLoaderThrowsParseException(): void
    {
        $path = '/path/to/config.yaml';

        $this->loader->expects($this->once())
            ->method('load')
            ->with($path)
            ->willThrowException(new ParseException('Parse error'));

        $this->expectException(ParseException::class);
        $this->subject->addConfiguration($this->loader, $path);
    }

    public function testAddConfigurationReturnsInstanceOfSelf(): void
    {
        $path = '/path/to/config.yaml';
        $config = [];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $result = $this->subject->addConfiguration($this->loader, $path);

        $this->assertInstanceOf(ConfigurationHandler::class, $result);
    }

    public function testGetTasksReturnsTasksFromConfiguration(): void
    {
        $path = '/path/to/config.yaml';
        $config = [
            'import' => [
                'tasks' => [
                    'test' => ['label' => 'Test Task']
                ]
            ],
            'export' => [
                'tasks' => [
                    'export' => ['label' => 'Export Task']
                ]
            ]
        ];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);

        $result = $this->subject->getTasks();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('import', $result);
        $this->assertArrayHasKey('test', $result['import']);
        $this->assertEquals('Test Task', $result['import']['test']['label']);

        $this->assertArrayHasKey('export', $result);
        $this->assertArrayHasKey('export', $result['export']);
        $this->assertEquals('Export Task', $result['export']['export']['label']);
    }

    public function testGetTasksReturnsEmptyArrayWhenNoTasksExist(): void
    {
        $path = '/path/to/config.yaml';
        $config = ['import' => ['sets' => ['testSet' => ['tasks' => 'test']]]];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);

        $result = $this->subject->getTasks();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSetsReturnsSetsFromConfiguration(): void
    {
        $path = '/path/to/config.yaml';
        $config = [
            'import' => [
                'sets' => [
                    'testSet' => [
                        'label' => 'Test Set',
                        'tasks' => 'test'
                    ]
                ]
            ],
            'export' => [
                'sets' => [
                    'exportSet' => [
                        'label' => 'Export Set',
                        'tasks' => 'export'
                    ]
                ]
            ]
        ];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);

        $result = $this->subject->getSets();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('import', $result);
        $this->assertArrayHasKey('testSet', $result['import']);
        $this->assertEquals('Test Set', $result['import']['testSet']['label']);
        $this->assertEquals('test', $result['import']['testSet']['tasks']);

        $this->assertArrayHasKey('export', $result);
        $this->assertArrayHasKey('exportSet', $result['export']);
        $this->assertEquals('Export Set', $result['export']['exportSet']['label']);
        $this->assertEquals('export', $result['export']['exportSet']['tasks']);
    }

    public function testGetSetsReturnsEmptyArrayWhenNoSetsExist(): void
    {
        $path = '/path/to/config.yaml';
        $config = ['import' => ['tasks' => ['test' => ['label' => 'Test Task']]]];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);

        $result = $this->subject->getSets();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFullConfigurationReturnsCompleteConfiguration(): void
    {
        $path = '/path/to/config.yaml';
        $config = [
            'import' => [
                'tasks' => [
                    'test' => ['label' => 'Test Task']
                ],
                'sets' => [
                    'testSet' => [
                        'label' => 'Test Set',
                        'tasks' => 'test'
                    ]
                ]
            ]
        ];

        $this->loader->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $this->subject->addConfiguration($this->loader, $path);

        $result = $this->subject->getFullConfiguration();

        $this->assertIsArray($result);
        $this->assertEquals($config, $result);
    }

    public function testAddConfigurationMergesMultipleConfigurations(): void
    {
        $path1 = '/path/to/config1.yaml';
        $config1 = [
            'import' => [
                'tasks' => [
                    'test1' => ['label' => 'Test Task 1']
                ]
            ]
        ];

        $path2 = '/path/to/config2.yaml';
        $config2 = [
            'import' => [
                'tasks' => [
                    'test2' => ['label' => 'Test Task 2']
                ]
            ]
        ];

        $this->loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnMap([
                [$path1, $config1],
                [$path2, $config2]
            ]);

        $this->subject->addConfiguration($this->loader, $path1);
        $this->subject->addConfiguration($this->loader, $path2);

        $result = $this->subject->getFullConfiguration();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('import', $result);
        $this->assertArrayHasKey('tasks', $result['import']);

        // Both tasks should be present
        $this->assertArrayHasKey('test1', $result['import']['tasks']);
        $this->assertEquals('Test Task 1', $result['import']['tasks']['test1']['label']);

        $this->assertArrayHasKey('test2', $result['import']['tasks']);
        $this->assertEquals('Test Task 2', $result['import']['tasks']['test2']['label']);
    }

    /**
     * @return array<string, array{array, array, array}>
     */
    public static function mergeDataProvider(): array
    {
        return [
            'scalar value of later configuration wins' => [
                ['task' => ['class' => 'A', 'label' => 'first']],
                ['task' => ['class' => 'B']],
                ['task' => ['class' => 'B', 'label' => 'first']],
            ],
            'list of later configuration replaces list' => [
                ['task' => ['fields' => ['a', 'b', 'c']]],
                ['task' => ['fields' => ['x']]],
                ['task' => ['fields' => ['x']]],
            ],
            'nested associative arrays are merged' => [
                ['task' => ['source' => ['class' => 'A', 'config' => ['file' => 'a.csv', 'delimiter' => ',']]]],
                ['task' => ['source' => ['config' => ['delimiter' => ';']]]],
                ['task' => ['source' => ['class' => 'A', 'config' => ['file' => 'a.csv', 'delimiter' => ';']]]],
            ],
            'associative array replaces scalar' => [
                ['task' => ['source' => 'A']],
                ['task' => ['source' => ['class' => 'B']]],
                ['task' => ['source' => ['class' => 'B']]],
            ],
            'numeric keys of associative arrays are kept' => [
                ['preProcessors' => [10 => ['class' => 'A'], 20 => ['class' => 'B']]],
                ['preProcessors' => [20 => ['class' => 'C']]],
                ['preProcessors' => [10 => ['class' => 'A'], 20 => ['class' => 'C']]],
            ],
        ];
    }

    #[Test]
    #[DataProvider('mergeDataProvider')]
    public function addConfigurationMergesWithLaterConfigurationWinning(array $first, array $second, array $expected): void
    {
        $this->loader->method('load')->willReturnMap([
            ['first.yaml', $first],
            ['second.yaml', $second],
        ]);

        $this->subject->addConfiguration($this->loader, 'first.yaml');
        $this->subject->addConfiguration($this->loader, 'second.yaml');

        $this->assertSame($expected, $this->subject->getFullConfiguration());
    }
}
