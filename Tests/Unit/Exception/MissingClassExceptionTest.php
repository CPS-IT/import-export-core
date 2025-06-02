<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Exception;

use CPSIT\ImportExportCore\Exception\MissingClassException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for MissingClassException
 */
#[CoversClass(MissingClassException::class)]
class MissingClassExceptionTest extends TestCase
{
    #[Test]
    public function extendsException(): void
    {
        $exception = new MissingClassException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    #[Test]
    public function canBeInstantiatedWithoutArguments(): void
    {
        $exception = new MissingClassException();
        $this->assertInstanceOf(MissingClassException::class, $exception);
        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithMessage(): void
    {
        $message = 'Class not found: SomeClass';
        $exception = new MissingClassException($message);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithMessageAndCode(): void
    {
        $message = 'Required class is missing';
        $code = 1404;
        $exception = new MissingClassException($message, $code);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithPreviousException(): void
    {
        $previous = new \RuntimeException('Class does not exist');
        $message = 'Class loading failed';
        $code = 1500;
        $exception = new MissingClassException($message, $code, $previous);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function canBeThrown(): void
    {
        $this->expectException(MissingClassException::class);
        $this->expectExceptionMessage('Test class not found');
        $this->expectExceptionCode(1404);
        
        throw new MissingClassException('Test class not found', 1404);
    }
}
