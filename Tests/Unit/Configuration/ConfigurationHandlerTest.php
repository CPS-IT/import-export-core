<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Configuration;

use CPSIT\ImportExportCore\Configuration\ConfigurationHandler;
use CPSIT\ImportExportCore\Configuration\ConfigurationLoaderInterface;
use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
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
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
                        'import' => [
                            'tasks' => [
                                'test' => ['label' => 'Test Task']
                            ]
                        ]
                    ]
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
        $config = ['module' => ['tx_t3importexport' => ['settings' => []]]];

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
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
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
                    ]
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
        $config = [
            'module' => [
                'tx_t3importexport' => [
                    'settings' => []
                ]
            ]
        ];

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
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
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
        $config = [
            'module' => [
                'tx_t3importexport' => [
                    'settings' => []
                ]
            ]
        ];

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
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
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
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
                        'import' => [
                            'tasks' => [
                                'test1' => ['label' => 'Test Task 1']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $path2 = '/path/to/config2.yaml';
        $config2 = [
            'module' => [
                'tx_t3importexport' => [
                    'settings' => [
                        'import' => [
                            'tasks' => [
                                'test2' => ['label' => 'Test Task 2']
                            ]
                        ]
                    ]
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
        $this->assertArrayHasKey('module', $result);
        $this->assertArrayHasKey('tx_t3importexport', $result['module']);
        $this->assertArrayHasKey('settings', $result['module']['tx_t3importexport']);
        $this->assertArrayHasKey('import', $result['module']['tx_t3importexport']['settings']);
        $this->assertArrayHasKey('tasks', $result['module']['tx_t3importexport']['settings']['import']);

        // Both tasks should be present
        $this->assertArrayHasKey('test1', $result['module']['tx_t3importexport']['settings']['import']['tasks']);
        $this->assertEquals('Test Task 1', $result['module']['tx_t3importexport']['settings']['import']['tasks']['test1']['label']);

        $this->assertArrayHasKey('test2', $result['module']['tx_t3importexport']['settings']['import']['tasks']);
        $this->assertEquals('Test Task 2', $result['module']['tx_t3importexport']['settings']['import']['tasks']['test2']['label']);
    }
}
