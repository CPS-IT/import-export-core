<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Tests\Unit\Component;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Component\NullComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for NullComponent
 */
#[CoversClass(NullComponent::class)]
class NullComponentTest extends TestCase
{
    protected NullComponent $subject;

    protected function setUp(): void
    {
        $this->subject = new NullComponent();
    }

    #[Test]
    public function classImplementsComponentInterface(): void
    {
        $this->assertInstanceOf(ComponentInterface::class, $this->subject);
    }

    #[Test]
    public function canBeInstantiated(): void
    {
        $this->assertInstanceOf(NullComponent::class, $this->subject);
    }

    #[Test]
    public function hasNoMethods(): void
    {
        $reflection = new \ReflectionClass(NullComponent::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        // Should only have inherited methods from parent classes/interfaces
        $declaredMethods = array_filter($methods, function ($method) {
            return $method->getDeclaringClass()->getName() === NullComponent::class;
        });
        
        $this->assertEmpty($declaredMethods, 'NullComponent should not declare any methods');
    }

    #[Test]
    public function canBeUsedAsNullObjectPattern(): void
    {
        // Test that the NullComponent can be used safely in place of other components
        $components = [new NullComponent(), new NullComponent()];
        
        foreach ($components as $component) {
            $this->assertInstanceOf(ComponentInterface::class, $component);
        }
        
        // Verify all components are NullComponent instances
        $this->assertContainsOnlyInstancesOf(NullComponent::class, $components);
    }
}