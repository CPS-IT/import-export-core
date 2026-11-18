# Writing components

A component is a class implementing one of the pipeline interfaces. The
processor calls it for each record or once per task (see
[Processing](Processing.md#order-of-processing)).

| Role | Interface | Method |
|---|---|---|
| initializer | `Pipeline\InitializerInterface` | `initialize(TaskContext $context): void` |
| data source | `Persistence\DataSourceInterface` | `getRecords(array $configuration): iterable` |
| filter | `Pipeline\FilterInterface` | `accepts(array $record): bool` |
| filter after pre-processing | `Pipeline\ProcessedRecordFilterInterface` | `acceptsProcessed(array $record, array $sourceRecord): bool` |
| pre-processor | `Pipeline\PreProcessorInterface` | `process(array $record): array` |
| converter | `Pipeline\ConverterInterface` | `convert(array $record): mixed` |
| post-processor | `Pipeline\PostProcessorInterface` | `process(mixed $convertedRecord, array $record): mixed` |
| data target | `Persistence\DataTargetInterface` | `persist(array\|object $object, ?array $configuration = null)`, `persistAll(mixed $result = null, ?array $configuration = null)` |
| finisher | `Pipeline\FinisherInterface` | `finish(TaskContext $context): void` |

Namespaces are relative to `CPSIT\ImportExportCore\`. Components return
values; they do not change their arguments. The interfaces in
`Component\` (e.g. `Component\PreProcessor\PreProcessorInterface`) are
deprecated, see [Legacy components](Legacy.md).

Sources and targets have optional interfaces, see
[Persistence](Persistence.md).

## Configuration

A component with options implements `ConfigurableInterface`, usually with
`ConfigurableTrait`. It receives its configuration once, when it is created.
`setConfiguration()` throws an `InvalidConfigurationException` if
`isConfigurationValid()` returns false, so an invalid configuration is found
when the task is created, not while records are processed.

```php
<?php

declare(strict_types=1);

namespace Vendor\Package\Processor;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Adds a prefix to the value of a field.
 *
 * Configuration:
 *
 * - `field` (string, required): the field
 * - `prefix` (string, default ''): the prefix
 */
final class PrefixPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        return isset($configuration['field']) && \is_string($configuration['field'])
            && \is_string($configuration['prefix'] ?? '');
    }

    public function process(array $record): array
    {
        $field = $this->configuration['field'];
        if (isset($record[$field]) && \is_string($record[$field])) {
            $record[$field] = ($this->configuration['prefix'] ?? '') . $record[$field];
        }

        return $record;
    }
}
```

`isConfigurationValid([])` is also called for components configured without
`config`. If the component cannot work without options, it returns false.

The option `disable` is evaluated by the processor; the component does not
need to accept or check it. `isConfigurationValid()` must not reject unknown
keys for that reason. See [Conditions](Conditions.md).

## Documenting options

The class docblock starts with a summary. The options follow under the line
`Configuration:` as a list, one item per option: name, type and `required` or
the default in parentheses, then the description. Continuation lines and
nested items are indented by two spaces. A component without options has the
line `Configuration: none.` (see
[decision 1](../Decisions/0001-documentation.md#docblock-convention)).

The reference [Configuration/Components.md](../Configuration/Components.md)
is generated from these docblocks for the components of the core:

```bash
composer docs:reference
```

`composer lint` fails if a component has no configuration section, if the
section does not follow the convention, or if the reference is not current.

## Dependencies

`ComponentFactory` creates components with an `InstantiatorInterface`:

* `Instantiator` (default) calls `new $className()`. Components must not
  require constructor arguments.
* `ContainerInstantiator` gets the component from a PSR-11 container and
  clones it, because containers usually share their services. Classes the
  container does not know are passed to a fallback instantiator
  (`Instantiator` by default).

Components with dependencies, e.g. `DataSourceDBAL` (a
`ConnectionProviderInterface`) or `ResolveReferencePreProcessor` (an
`IdMapInterface`), declare them in the constructor and are registered in the
container of the application.

Because every task gets its own clone, a component may keep state for one
task, e.g. a cache of looked-up values. It must not rely on state of other
tasks.

## Registering short names

A configuration names the class of a component in `class`. Besides the fully
qualified class name, it may use a short name registered for the role:

```php
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

$registry = new ComponentRegistry();
$registry->register(PreProcessorInterface::class, 'prefix', PrefixPreProcessor::class);
$registry->setDefault(DataTargetInterface::class, MyDefaultTarget::class);
```

```yaml
preProcessors:
  10:
    class: prefix
    config:
      field: title
      prefix: 'News: '
```

Names are registered per role, so the same name can be used for a
pre-processor and a post-processor. A configuration without `class` uses the
default class of the role. `register()` and `setDefault()` throw an
`InvalidClassException` if the class does not implement the role's interface
(or its deprecated counterpart).

## Messages

A component reports errors and notices with `LoggingInterface`, usually with
`LoggingTrait`. After each call, the processor moves the messages of the
component into the result (`ResultInterface::getMessages()`).

```php
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;

final class LookUp implements PreProcessorInterface, LoggingInterface
{
    use LoggingTrait;

    public const ERROR_CODES = [
        1795100001 => ['Missing reference', 'Record %s refers to %s, which does not exist.'],
    ];

    public function process(array $record): array
    {
        // ...
        $this->logError(1795100001, [$record['uid'], $record['parent']]);

        return $record;
    }
}
```

`LoggingTrait` reads the codes from the constants `ERROR_CODES` and
`NOTICE_CODES`. Message IDs are unique numbers, by convention a Unix
timestamp. Configurations can react to them with
`disable: {if: {result: {hasMessage: '1795100001'}}}`.

## Tests

Components of the core have unit tests in `Tests/Unit/`, in the same
namespace structure as `Classes/`. A component should be tested with valid and
invalid configurations, and with records that miss fields or hold unexpected
types. Unit tests must not need a database; DBAL components are tested with
SQLite in memory.
