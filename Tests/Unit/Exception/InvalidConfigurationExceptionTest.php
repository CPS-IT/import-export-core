<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Exception;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for InvalidConfigurationException
 */
#[CoversClass(InvalidConfigurationException::class)]
class InvalidConfigurationExceptionTest extends TestCase
{
    #[Test]
    public function extendsException(): void
    {
        $exception = new InvalidConfigurationException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    #[Test]
    public function canBeInstantiatedWithoutArguments(): void
    {
        $exception = new InvalidConfigurationException();
        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);
        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithMessage(): void
    {
        $message = 'Invalid configuration provided';
        $exception = new InvalidConfigurationException($message);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithMessageAndCode(): void
    {
        $message = 'Configuration validation failed';
        $code = 1001;
        $exception = new InvalidConfigurationException($message, $code);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    #[Test]
    public function canBeInstantiatedWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $message = 'Configuration error';
        $code = 1002;
        $exception = new InvalidConfigurationException($message, $code, $previous);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function canBeThrown(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Test configuration error');
        $this->expectExceptionCode(1003);
        
        throw new InvalidConfigurationException('Test configuration error', 1003);
    }
}
