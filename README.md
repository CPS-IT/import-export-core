# ImportExportCore

Core library providing base functionality for import and export operations.

## Features

- MessageContainer for handling messages during processing
- Common interfaces and implementations

## Installation

```bash
composer require cpsit/import-export-core
```

## Usage

```php
use CPSIT\ImportExportCore\Messaging\MessageContainer;

$container = new MessageContainer();
$container->addMessage("Processing started", 0);
// Do work...
$container->addMessage("Processing completed", 0);

// Get all messages
$messages = $container->getMessages();
```