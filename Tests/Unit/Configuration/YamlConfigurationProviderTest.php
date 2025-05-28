<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Configuration;

use CPSIT\ImportExportCore\Configuration\ConfigurationHandler;
use CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader;
use CPSIT\ImportExportCore\Configuration\YamlConfigurationProvider;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class YamlConfigurationProviderTest extends TestCase
{
    protected YamlConfigurationProvider $subject;
    protected ConfigurationHandler $configurationHandler;
    protected YamlConfigurationLoader $yamlLoader;
    protected vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->configurationHandler = $this->getMockBuilder(ConfigurationHandler::class)
            ->getMock();
        $this->yamlLoader = $this->getMockBuilder(YamlConfigurationLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = new YamlConfigurationProvider(
            $this->configurationHandler,
            $this->yamlLoader
        );

        $this->root = vfsStream::setup('home');
    }

    public function testLoadFromDirectoryProcessesYamlFiles(): void
    {
        vfsStream::newFile('config1.yaml')->at($this->root)->withContent('');
        vfsStream::newFile('config2.yaml')->at($this->root)->withContent('');
        vfsStream::newFile('other.txt')->at($this->root)->withContent('');

        $matcher = $this->exactly(2);
        $this->configurationHandler->expects($matcher)
            ->method('addConfiguration')
            ->willReturnCallback(function ($loader, $path) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $this->assertSame($this->yamlLoader, $loader);
                    $this->assertEquals($this->root->url() . '/config1.yaml', $path);
                } elseif ($callCount === 2) {
                    $this->assertSame($this->yamlLoader, $loader);
                    $this->assertEquals($this->root->url() . '/config2.yaml', $path);
                }

                return $this->configurationHandler;
            });

        $this->configurationHandler->expects($this->once())
            ->method('getFullConfiguration')
            ->willReturn(['some' => 'config']);

        $result = $this->subject->loadFromDirectory($this->root->url());

        $this->assertEquals(['some' => 'config'], $result);
    }

    public function testLoadFromDirectoryHandlesEmptyDirectory(): void
    {
        $this->configurationHandler->expects($this->never())
            ->method('addConfiguration');

        $this->configurationHandler->expects($this->once())
            ->method('getFullConfiguration')
            ->willReturn([]);

        $result = $this->subject->loadFromDirectory($this->root->url());

        $this->assertEquals([], $result);
    }

    public function testLoadFromDirectoryWithCustomExtension(): void
    {
        vfsStream::newFile('config1.yml')->at($this->root)->withContent('');
        vfsStream::newFile('config2.yml')->at($this->root)->withContent('');
        vfsStream::newFile('config3.yaml')->at($this->root)->withContent('');

        $matcher = $this->exactly(2);
        $this->configurationHandler->expects($matcher)
            ->method('addConfiguration')
            ->willReturnCallback(function ($loader, $path) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $this->assertSame($this->yamlLoader, $loader);
                    $this->assertEquals($this->root->url() . '/config1.yml', $path);
                } elseif ($callCount === 2) {
                    $this->assertSame($this->yamlLoader, $loader);
                    $this->assertEquals($this->root->url() . '/config2.yml', $path);
                }

                return $this->configurationHandler;
            });

        $this->configurationHandler->expects($this->once())
            ->method('getFullConfiguration')
            ->willReturn(['some' => 'config']);

        $result = $this->subject->loadFromDirectory($this->root->url(), 'yml');

        $this->assertEquals(['some' => 'config'], $result);
    }
}
