<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Service;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use CPSIT\ImportExportCore\Exception\ParseException;
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class YamlConfigurationParserTest extends TestCase
{
    protected YamlConfigurationParser $subject;
    protected vfsStreamDirectory $root;
    
    protected function setUp(): void
    {
        $this->subject = new YamlConfigurationParser();
        $this->root = vfsStream::setup('home');
    }
    
    public function testParseFileThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->subject->parseFile('/non/existent/file.yaml');
    }
    
    public function testParseFileReturnsArrayForValidYamlFile(): void
    {
        $yamlContent = <<<YAML
import:
  tasks:
    test:
      label: "Test Task"
YAML;
        
        $fileName = 'config.yaml';
        vfsStream::newFile($fileName)
            ->at($this->root)
            ->withContent($yamlContent);
        
        $result = $this->subject->parseFile($this->root->url() . '/' . $fileName);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('import', $result);
        $this->assertArrayHasKey('tasks', $result['import']);
        $this->assertArrayHasKey('test', $result['import']['tasks']);
        $this->assertEquals('Test Task', $result['import']['tasks']['test']['label']);
    }
    
    public function testParseFileThrowsExceptionForInvalidYamlContent(): void
    {
        $yamlContent = <<<YAML
import:
  tasks:
    test:
      label: "Test Task
      invalid: yaml
YAML;
        
        $fileName = 'invalid.yaml';
        vfsStream::newFile($fileName)
            ->at($this->root)
            ->withContent($yamlContent);
        
        $this->expectException(ParseException::class);
        $this->subject->parseFile($this->root->url() . '/' . $fileName);
    }
    
    public function testParseStringReturnsArrayForValidYamlString(): void
    {
        $yamlContent = <<<YAML
import:
  tasks:
    test:
      label: "Test Task"
YAML;
        
        $result = $this->subject->parseString($yamlContent);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('import', $result);
        $this->assertArrayHasKey('tasks', $result['import']);
        $this->assertArrayHasKey('test', $result['import']['tasks']);
        $this->assertEquals('Test Task', $result['import']['tasks']['test']['label']);
    }
    
    public function testParseStringThrowsExceptionForInvalidYamlString(): void
    {
        $yamlContent = <<<YAML
import:
  tasks:
    test:
      label: "Test Task
      invalid: yaml
YAML;
        
        $this->expectException(ParseException::class);
        $this->subject->parseString($yamlContent);
    }
}