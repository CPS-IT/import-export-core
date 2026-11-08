# Import & Export core library

Core library of the TYPO3 extension
[Import & Export](https://github.com/dwenzel/t3import_export)
(`t3import_export`). It contains the parts of the import and export
functionality that do not depend on TYPO3:

* component interfaces (initializers, pre-processors, converters,
  post-processors, finishers) and traits for configurable, identifiable and
  logging components
* persistence interfaces for data sources and data targets
* messaging (`Message`, `MessageContainer`) and the task result model
* configuration handling, including loading and parsing YAML configuration
* exceptions

The library is in an early stage. While the version is `0.x`, a new minor
version may contain breaking changes.

## Requirements

* PHP 8.3 or newer
* `symfony/yaml` 6 or 7

## Installation

```bash
composer require cpsit/import-export-core
```

## Usage

Collect messages during processing:

```php
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Messaging\MessageContainer;

$container = new MessageContainer();
$container->addMessage(new Message('3 records imported', 'Import finished'));
$container->addMessage(
    new Message(
        'Column "title" is empty',
        'Record 2 skipped',
        Message::SEVERITY_WARNING
    )
);

foreach ($container->getMessages() as $message) {
    echo $message->getTitle() . ': ' . $message->getMessage() . PHP_EOL;
}
```

Load a YAML configuration file:

```php
use CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader;
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;

$loader = new YamlConfigurationLoader(new YamlConfigurationParser());
$configuration = $loader->load('config/import.yaml');
```

The loader returns the tasks and sets of the import and export sections:

```php
[
    'import' => ['tasks' => [/* identifier => task */], 'sets' => [/* ... */]],
    'export' => ['tasks' => [/* ... */], 'sets' => [/* ... */]],
]
```

Several files can be combined with `ConfigurationHandler::addConfiguration()`;
values of later files replace those of earlier ones.

## Development

```bash
composer install
composer test:unit    # unit tests
composer lint         # composer normalize, editorconfig, PHP-CS-Fixer
composer sca          # PHPStan
```

See [Documentation/Architecture.md](Documentation/Architecture.md) for the
structure of the library and the planned changes, and
[CONTRIBUTING.md](CONTRIBUTING.md) for the branching model, the workflow and
the release process. [UPGRADE.md](UPGRADE.md) describes how to migrate code
for breaking changes.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
