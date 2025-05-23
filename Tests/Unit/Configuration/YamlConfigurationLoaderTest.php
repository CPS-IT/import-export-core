<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Configuration;

use CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader;
use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;
use PHPUnit\Framework\TestCase;

class YamlConfigurationLoaderTest extends TestCase
{
    protected YamlConfigurationLoader $subject;
    protected YamlConfigurationParser $parser;
    
    protected function setUp(): void
    {
        $this->parser = $this->createMock(YamlConfigurationParser::class);
        $this->subject = new YamlConfigurationLoader($this->parser);
    }
    
    public function testLoadCallsParserWithGivenPath(): void
    {
        $path = '/path/to/config.yaml';
        $parsedConfig = ['import' => ['tasks' => ['test' => ['label' => 'Test Task']]]];
        
        $this->parser->expects($this->once())
            ->method('parseFile')
            ->with($path)
            ->willReturn($parsedConfig);
        
        $this->subject->load($path);
    }
    
    public function testLoadThrowsExceptionWhenParserThrowsFileNotFoundException(): void
    {
        $path = '/path/to/config.yaml';
        
        $this->parser->expects($this->once())
            ->method('parseFile')
            ->with($path)
            ->willThrowException(new FileNotFoundException('File not found'));
        
        $this->expectException(FileNotFoundException::class);
        $this->subject->load($path);
    }
    
    public function testLoadThrowsExceptionWhenParserThrowsParseException(): void
    {
        $path = '/path/to/config.yaml';
        
        $this->parser->expects($this->once())
            ->method('parseFile')
            ->with($path)
            ->willThrowException(new ParseException('Parse error'));
        
        $this->expectException(ParseException::class);
        $this->subject->load($path);
    }
    
    public function testLoadConvertsYamlConfigurationToTypoScriptFormat(): void
    {
        $path = '/path/to/config.yaml';
        $parsedConfig = [
            'import' => [
                'tasks' => [
                    'test' => [
                        'label' => 'Test Task'
                    ]
                ],
                'sets' => [
                    'testSet' => [
                        'label' => 'Test Set',
                        'tasks' => 'test'
                    ]
                ]
            ],
            'export' => [
                'tasks' => [
                    'export' => [
                        'label' => 'Export Task'
                    ]
                ],
                'sets' => [
                    'exportSet' => [
                        'label' => 'Export Set',
                        'tasks' => 'export'
                    ]
                ]
            ]
        ];
        
        $this->parser->expects($this->once())
            ->method('parseFile')
            ->with($path)
            ->willReturn($parsedConfig);
        
        $result = $this->subject->load($path);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('module', $result);
        $this->assertArrayHasKey('tx_t3importexport', $result['module']);
        $this->assertArrayHasKey('settings', $result['module']['tx_t3importexport']);
        
        // Check import tasks
        $this->assertArrayHasKey('import', $result['module']['tx_t3importexport']['settings']);
        $this->assertArrayHasKey('tasks', $result['module']['tx_t3importexport']['settings']['import']);
        $this->assertArrayHasKey('test', $result['module']['tx_t3importexport']['settings']['import']['tasks']);
        $this->assertEquals('Test Task', $result['module']['tx_t3importexport']['settings']['import']['tasks']['test']['label']);
        
        // Check import sets
        $this->assertArrayHasKey('sets', $result['module']['tx_t3importexport']['settings']['import']);
        $this->assertArrayHasKey('testSet', $result['module']['tx_t3importexport']['settings']['import']['sets']);
        $this->assertEquals('Test Set', $result['module']['tx_t3importexport']['settings']['import']['sets']['testSet']['label']);
        
        // Check export tasks
        $this->assertArrayHasKey('export', $result['module']['tx_t3importexport']['settings']);
        $this->assertArrayHasKey('tasks', $result['module']['tx_t3importexport']['settings']['export']);
        $this->assertArrayHasKey('export', $result['module']['tx_t3importexport']['settings']['export']['tasks']);
        $this->assertEquals('Export Task', $result['module']['tx_t3importexport']['settings']['export']['tasks']['export']['label']);
        
        // Check export sets
        $this->assertArrayHasKey('sets', $result['module']['tx_t3importexport']['settings']['export']);
        $this->assertArrayHasKey('exportSet', $result['module']['tx_t3importexport']['settings']['export']['sets']);
        $this->assertEquals('Export Set', $result['module']['tx_t3importexport']['settings']['export']['sets']['exportSet']['label']);
    }
    
    public function testLoadHandlesPartialConfiguration(): void
    {
        $path = '/path/to/config.yaml';
        $parsedConfig = [
            'import' => [
                'tasks' => [
                    'test' => [
                        'label' => 'Test Task'
                    ]
                ]
            ]
        ];
        
        $this->parser->expects($this->once())
            ->method('parseFile')
            ->with($path)
            ->willReturn($parsedConfig);
        
        $result = $this->subject->load($path);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('module', $result);
        $this->assertArrayHasKey('tx_t3importexport', $result['module']);
        $this->assertArrayHasKey('settings', $result['module']['tx_t3importexport']);
        $this->assertArrayHasKey('import', $result['module']['tx_t3importexport']['settings']);
        $this->assertArrayHasKey('tasks', $result['module']['tx_t3importexport']['settings']['import']);
        $this->assertArrayHasKey('test', $result['module']['tx_t3importexport']['settings']['import']['tasks']);
        $this->assertEquals('Test Task', $result['module']['tx_t3importexport']['settings']['import']['tasks']['test']['label']);
        
        // These keys should not exist
        $this->assertArrayNotHasKey('sets', $result['module']['tx_t3importexport']['settings']['import']);
        $this->assertArrayNotHasKey('export', $result['module']['tx_t3importexport']['settings']);
    }
}