<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Exception;

use CPSIT\ImportExportCore\Exception\FileNotFoundException;
use PHPUnit\Framework\TestCase;

class FileNotFoundExceptionTest extends TestCase
{
    public function testExceptionExtendsBaseException(): void
    {
        $exception = new FileNotFoundException('Test message');
        $this->assertInstanceOf(\Exception::class, $exception);
    }
    
    public function testExceptionStoresMessage(): void
    {
        $message = 'File not found: /path/to/file.yaml';
        $exception = new FileNotFoundException($message);
        $this->assertEquals($message, $exception->getMessage());
    }
    
    public function testExceptionStoresCode(): void
    {
        $code = 123;
        $exception = new FileNotFoundException('Test message', $code);
        $this->assertEquals($code, $exception->getCode());
    }
    
    public function testExceptionStoresPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new FileNotFoundException('Test message', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
