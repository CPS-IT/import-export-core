<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Exception;

use CPSIT\ImportExportCore\Exception\ParseException;
use PHPUnit\Framework\TestCase;

class ParseExceptionTest extends TestCase
{
    public function testExceptionExtendsBaseException(): void
    {
        $exception = new ParseException('Test message');
        $this->assertInstanceOf(\Exception::class, $exception);
    }
    
    public function testExceptionStoresMessage(): void
    {
        $message = 'Error parsing YAML: Unexpected end of line';
        $exception = new ParseException($message);
        $this->assertEquals($message, $exception->getMessage());
    }
    
    public function testExceptionStoresCode(): void
    {
        $code = 456;
        $exception = new ParseException('Test message', $code);
        $this->assertEquals($code, $exception->getCode());
    }
    
    public function testExceptionStoresPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ParseException('Test message', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}