# Proposed Interface Design

This document outlines the key interfaces and abstract classes that should be implemented in the `cpsit/import-export-core` library to provide a solid foundation for the import/export functionality.

## Core Interfaces

### ConfigurableInterface

```php
interface ConfigurableInterface
{
    /**
     * Sets configuration options
     *
     * @param array $configuration
     * @return void
     */
    public function setConfiguration(array $configuration): void;
    
    /**
     * Gets configuration
     *
     * @return array
     */
    public function getConfiguration(): array;
}
```

### IdentifiableInterface

```php
interface IdentifiableInterface
{
    /**
     * Gets the identifier
     *
     * @return string
     */
    public function getIdentifier(): string;
    
    /**
     * Sets the identifier
     *
     * @param string $identifier
     * @return void
     */
    public function setIdentifier(string $identifier): void;
}
```

### Messaging Interfaces

```php
interface MessageContainerInterface
{
    /**
     * Adds a message
     *
     * @param string $message
     * @param int $severity
     * @return void
     */
    public function addMessage(string $message, int $severity = 0): void;
    
    /**
     * Returns all messages
     *
     * @return array
     */
    public function getMessages(): array;
    
    /**
     * Returns whether any messages exist
     *
     * @return bool
     */
    public function hasMessages(): bool;
    
    /**
     * Clears all messages
     *
     * @return void
     */
    public function clearMessages(): void;
}
```

## Component Interfaces

### Component Base Interface

```php
interface ComponentInterface extends ConfigurableInterface, IdentifiableInterface
{
    /**
     * Process the data
     *
     * @param mixed $data
     * @return mixed
     */
    public function process(mixed $data): mixed;
}
```

### Specialized Component Interfaces

```php
interface InitializerInterface extends ComponentInterface
{
    /**
     * Initialize the import/export process
     *
     * @return bool
     */
    public function initialize(): bool;
}

interface ConverterInterface extends ComponentInterface
{
    /**
     * Convert from one format to another
     *
     * @param mixed $data
     * @return mixed
     */
    public function convert(mixed $data): mixed;
}

interface PreProcessorInterface extends ComponentInterface
{
    /**
     * Pre-process data before import/export
     *
     * @param mixed $data
     * @return mixed
     */
    public function preProcess(mixed $data): mixed;
}

interface PostProcessorInterface extends ComponentInterface
{
    /**
     * Post-process data after import/export
     *
     * @param mixed $data
     * @return mixed
     */
    public function postProcess(mixed $data): mixed;
}

interface FinisherInterface extends ComponentInterface
{
    /**
     * Finish the import/export process
     *
     * @param mixed $data
     * @return bool
     */
    public function finish(mixed $data): bool;
}
```

## Data Source/Target Interfaces

```php
interface DataSourceInterface extends ConfigurableInterface, IdentifiableInterface
{
    /**
     * Get data from the source
     *
     * @return mixed
     */
    public function getData(): mixed;
    
    /**
     * Check if source has data
     *
     * @return bool
     */
    public function hasData(): bool;
}

interface DataTargetInterface extends ConfigurableInterface, IdentifiableInterface
{
    /**
     * Persist data to the target
     *
     * @param mixed $data
     * @return bool
     */
    public function persist(mixed $data): bool;
}
```

## Resource Interfaces

```php
interface ResourceStorageInterface
{
    /**
     * Get a resource by identifier
     *
     * @param string $identifier
     * @return mixed
     */
    public function getResource(string $identifier): mixed;
    
    /**
     * Store a resource
     *
     * @param mixed $resource
     * @param string $identifier
     * @return bool
     */
    public function storeResource(mixed $resource, string $identifier): bool;
    
    /**
     * Check if a resource exists
     *
     * @param string $identifier
     * @return bool
     */
    public function hasResource(string $identifier): bool;
}
```

## Factory Interfaces

```php
interface ComponentFactoryInterface
{
    /**
     * Create a component from configuration
     *
     * @param array $configuration
     * @return ComponentInterface
     */
    public function createFromConfiguration(array $configuration): ComponentInterface;
}

interface DataSourceFactoryInterface
{
    /**
     * Create a data source from configuration
     *
     * @param array $configuration
     * @return DataSourceInterface
     */
    public function createFromConfiguration(array $configuration): DataSourceInterface;
}

interface DataTargetFactoryInterface
{
    /**
     * Create a data target from configuration
     *
     * @param array $configuration
     * @return DataTargetInterface
     */
    public function createFromConfiguration(array $configuration): DataTargetInterface;
}
```

## Task and Result Interfaces

```php
interface TaskInterface extends ConfigurableInterface, IdentifiableInterface
{
    /**
     * Execute the task
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface;
}

interface ResultInterface extends \Iterator, \Countable
{
    /**
     * Add a result item
     *
     * @param mixed $item
     * @return void
     */
    public function addItem(mixed $item): void;
    
    /**
     * Get all result items
     *
     * @return array
     */
    public function getItems(): array;
    
    /**
     * Set success status
     *
     * @param bool $success
     * @return void
     */
    public function setSuccess(bool $success): void;
    
    /**
     * Get success status
     *
     * @return bool
     */
    public function isSuccess(): bool;
}
```

## Implementation Strategy

1. Start with the most fundamental interfaces (Configurable, Identifiable, MessageContainer)
2. Implement base traits and classes for these interfaces
3. Move on to component interfaces
4. Implement result and task interfaces
5. Build more specialized components on top of the base interfaces

By implementing these interfaces in the core library, we will create a solid foundation that can be extended for specific use cases in the TYPO3 extension or other applications.