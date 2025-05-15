# Component Analysis: t3import_export

This document analyzes the components in the existing `cpsit/t3import_export` extension that are candidates for extraction to the framework-agnostic `cpsit/import-export-core` library.

## Traits

### 1. MessageContainerTrait

**Current location**: `Classes/Messaging/MessageContainerTrait.php`  
**PHPStan issues**: Many `property.notFound` errors with `$messageContainer`  
**Extraction plan**: 
- Move to core library
- Ensure property is properly initialized
- Update to use the new `MessageContainer` class

### 2. ConfigurableTrait

**Current location**: `Classes/ConfigurableTrait.php`  
**PHPStan issues**: None identified  
**Extraction plan**: 
- Move to core library as-is
- Create corresponding interface

### 3. IdentifiableTrait

**Current location**: `Classes/IdentifiableTrait.php`  
**PHPStan issues**: None identified  
**Extraction plan**: 
- Move to core library as-is
- Create corresponding interface

### 4. RenderContentTrait

**Current location**: `Classes/RenderContentTrait.php`  
**PHPStan issues**: Multiple `property.notFound` errors with `$contentObjectRenderer` and `$typoScriptService`  
**Extraction plan**: 
- Extract framework-agnostic parts to core library
- Leave TYPO3-specific content rendering in extension
- Create adapter interfaces for framework-specific implementations

### 5. ResourceStorageTrait

**Current location**: `Classes/Resource/ResourceStorageTrait.php`  
**PHPStan issues**: Multiple `property.notFound` errors with `$resourceStorage`  
**Extraction plan**: 
- Create abstraction for resource storage in core library
- Leave TYPO3-specific implementation in extension
- Create adapter interface for framework-specific implementations

### 6. LoggingTrait

**Current location**: `Classes/LoggingTrait.php`  
**PHPStan issues**: `property.notFound` errors with `$messageContainer`  
**Extraction plan**: 
- Move to core library
- Update to use PSR-3 Logger
- Ensure property is properly initialized

## Components

### 1. Component Base Classes

**Current location**: `Classes/Component/AbstractComponent.php` and others  
**PHPStan issues**: Various property and method issues  
**Extraction plan**: 
- Create component interfaces in core library
- Extract common base implementations to core library
- Leave TYPO3-specific implementations in extension

### 2. Domain Models

**Current location**: `Classes/Domain/Model/`  
**PHPStan issues**: Various issues with `TaskResult` class  
**Extraction plan**: 
- Extract base domain interfaces and models to core library
- Leave TYPO3-specific implementations in extension

### 3. Factories

**Current location**: `Classes/Domain/Factory/` and `Classes/Persistence/Factory/`  
**PHPStan issues**: Various dependency issues  
**Extraction plan**: 
- Extract factory interfaces to core library
- Create abstract factory implementations in core
- Leave TYPO3-specific factory implementations in extension

## Components to Keep in Extension

The following components should remain in the TYPO3 extension:

1. **TYPO3-specific Controllers**: `Classes/Controller/`
2. **TYPO3-specific Commands**: `Classes/Command/`
3. **Extension Configuration**: `Classes/Configuration/`
4. **TYPO3-specific Persistence**: `Classes/Persistence/`
5. **TYPO3 Service Implementations**: `Classes/Service/`

## Approach for Extracting Components

1. **Create interfaces** in the core library
2. **Extract core functionality** to framework-agnostic implementations
3. **Create adapters** in the extension for TYPO3-specific functionality
4. **Update extension** to use core library components

## Example: MessageContainer Refactoring

**Before** (in extension):
```php
trait MessageContainerTrait
{
    protected $messageContainer;
    
    // Methods using $messageContainer
}

// Usage:
class SomeClass 
{
    use MessageContainerTrait;
    
    public function doSomething()
    {
        $this->messageContainer->addMessage('message');
    }
}
```

**After** (in core):
```php
// In core library:
interface MessageContainerInterface { /* ... */ }
class MessageContainer implements MessageContainerInterface { /* ... */ }

trait MessageContainerTrait 
{
    protected MessageContainerInterface $messageContainer;
    
    public function getMessageContainer(): MessageContainerInterface
    {
        if (!isset($this->messageContainer)) {
            $this->messageContainer = new MessageContainer();
        }
        return $this->messageContainer;
    }
    
    // Other methods
}

// In extension:
class SomeClass 
{
    use \CPSIT\ImportExportCore\Messaging\MessageContainerTrait;
    
    public function doSomething()
    {
        $this->getMessageContainer()->addMessage('message');
    }
}
```

This approach resolves PHPStan issues and improves the overall architecture.