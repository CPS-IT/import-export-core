# Implementation Plan: Refactoring from t3import_export to import-export-core

This document outlines the strategy for extracting and refactoring core functionality from `cpsit/t3import_export` into the new framework-agnostic `cpsit/import-export-core` library.

## 1. Objectives

- Extract framework-agnostic code from t3import_export
- Create a clean, modular core library without TYPO3-specific dependencies
- Refactor t3import_export to depend on the core library
- Improve maintainability and testability
- Address PHPStan warnings and issues

## 2. Components to Extract

Based on the PHPStan warnings and code structure analysis, the following components should be extracted:

### 2.1 Messaging Components

- [x] `MessageContainerInterface` and `MessageContainer` implementation *(already implemented)*
- [ ] `MessageContainerTrait` - for easy integration in various classes
- [ ] Logging functionality (currently in `LoggingTrait`)

### 2.2 Resource Handling

- [ ] `ResourceStorageTrait` and corresponding interface
- [ ] File handling utilities
- [ ] Path handling components

### 2.3 Core Interfaces

- [ ] `IdentifiableInterface` and corresponding trait
- [ ] `ConfigurableInterface` and corresponding trait
- [ ] Component interfaces (Converter, Processor, etc.)

### 2.4 Domain Model Base Classes

- [ ] Basic result models (`TaskResult` base class)
- [ ] Transfer model interfaces and abstractions

### 2.5 Content Rendering

- [ ] Framework-agnostic parts of `RenderContentTrait`

## 3. Implementation Phases

### Phase 1: Core Infrastructure

1. Extract and implement core interfaces and traits:
   - `ConfigurableInterface` and `ConfigurableTrait`
   - `IdentifiableInterface` and `IdentifiableTrait`
   - Complete the messaging components

2. Implement unit tests for all extracted components

### Phase 2: Component Framework

1. Extract component interfaces and base implementations:
   - Component interfaces (Initializer, Converter, PreProcessor, etc.)
   - Abstract base classes for components
   - Factory interfaces and base implementations

2. Implement unit tests for component framework

### Phase 3: Resource Handling

1. Extract file and resource handling components:
   - Resource storage abstraction
   - File handling utilities
   - Path generation

2. Implement unit tests for resource components

### Phase 4: Domain Models

1. Extract domain model base classes and interfaces:
   - Task and result models
   - Transfer interfaces
   - Data representation models

2. Implement unit tests for domain models

### Phase 5: Integration

1. Update t3import_export to use the core library
2. Implement TYPO3-specific adapters in t3import_export
3. Update namespaces and dependencies
4. Run PHPStan analysis to verify improvements

## 4. Dependency Strategy

### 4.1 Core Library Dependencies

The core library should have minimal dependencies:

```
"require": {
    "php": ">=8.4.0",
    "psr/log": "^3.0"
}
```

### 4.2 Extension Dependencies

The TYPO3 extension should depend on the core library:

```
"require": {
    "cpsit/import-export-core": "^1.0",
    "typo3/cms-core": "^13.4"
}
```

## 5. Namespace Structure

### 5.1 Core Library

```
CPSIT\ImportExportCore\
  ├── Component\
  │   ├── Initializer\
  │   ├── Converter\
  │   ├── PreProcessor\
  │   ├── PostProcessor\
  │   └── Finisher\
  ├── Domain\
  │   ├── Model\
  │   └── Factory\
  ├── Messaging\
  ├── Resource\
  └── Utility\
```

### 5.2 TYPO3 Extension

```
CPSIT\T3importExport\
  ├── Adapter\          (New: TYPO3-specific adapters)
  ├── Command\
  ├── Component\        (TYPO3-specific components)
  ├── Configuration\
  ├── Controller\
  ├── Domain\
  ├── Persistence\
  └── Service\
```

## 6. PHPStan Issue Resolution

The refactoring will specifically address these categories of PHPStan warnings:

1. `property.notFound` issues with `$messageContainer`, `$configurationManager`
   - Solution: Use proper dependency injection with properly typed properties

2. `return.missing` issues
   - Solution: Ensure all methods have explicit return statements

3. Method compatibility issues (Iterator implementations)
   - Solution: Implement proper return types with `#[\ReturnTypeWillChange]` where needed

4. Undefined class issues
   - Solution: Either implement missing classes or update dependencies

## 7. Testing Strategy

1. Implement comprehensive unit tests for the core library
2. Use test doubles to avoid framework dependencies
3. Ensure backward compatibility through integration tests

## 8. Documentation

1. Document all public APIs of the core library
2. Provide usage examples for common patterns
3. Document the adapter strategy for framework integration

## 9. Roadmap and Milestones

1. **Initial Setup**: Core interfaces and messaging components
   - Target date: [Set appropriate date]

2. **Component Framework**: Base component abstractions and factories
   - Target date: [Set appropriate date]

3. **Resource Handling**: File and resource components
   - Target date: [Set appropriate date]

4. **TYPO3 Integration**: TYPO3 adapters and interface implementations
   - Target date: [Set appropriate date]

5. **Release**: Stable release of core library and updated extension
   - Target date: [Set appropriate date]