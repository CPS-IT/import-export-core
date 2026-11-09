# Upgrade notes

This file describes breaking changes and deprecations, and how to migrate.
Each section covers one version. Entries are added with the pull request that
introduces the change (see [CONTRIBUTING.md](CONTRIBUTING.md)).

Each entry contains:

* **Affects**: who has to act (implementations of an interface, callers of a
  class, configuration)
* **Change**: what changed
* **Migration**: what to do, with an example where useful

[CHANGELOG.md](CHANGELOG.md) lists all changes, including non-breaking ones.

## 0.2.0 (unreleased)

### Component interfaces: `isDisabled()` accepts `ResultInterface`

Issue: [#22](https://github.com/CPS-IT/import-export-core/issues/22)

**Affects:** implementations of `InitializerInterface`,
`PreProcessorInterface`, `ConverterInterface`, `PostProcessorInterface` and
`FinisherInterface`.

**Change:** the third parameter of `isDisabled()` is typed
`?ResultInterface` instead of `?TaskResult`. `TaskResult` implements
`ResultInterface`.

**Migration:** change the parameter type. Code that relies on methods of
`TaskResult` not declared in `ResultInterface` (e.g. `removeElement()`) must
check the type first.

```php
// before
public function isDisabled(array $configuration, array $record = [], ?TaskResult $result = null): bool

// after
public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool
```

### Data targets: `DisableableInterface`

Issue: [#22](https://github.com/CPS-IT/import-export-core/issues/22)

**Affects:** data targets that support being disabled per record.

**Change:** the processor checks whether a data target is disabled only if
the target implements `Component\DisableableInterface`. Before, the extension's
processor checked for the extension's `AbstractComponent`.

**Migration:** implement `DisableableInterface` in data targets (or their base
class) that support `disabled`. Targets that do not implement it are never
skipped.

### Processor moved into the core

Issue: [#22](https://github.com/CPS-IT/import-export-core/issues/22)

**Affects:** code that uses the extension's `DataTransferProcessor`,
`TransferTask`, `TransferSet`, `DataStream`, `DataStreamInterface`,
`DemandInterface` or `TaskDemand`.

**Change:** these classes are part of the core
(`CPSIT\ImportExportCore\Service\` and `CPSIT\ImportExportCore\Domain\Model\`).
The processor has a different API:

* `process()` takes one `TransferTaskInterface` and returns a
  `ResultInterface`. `DemandProcessor::process()` processes all tasks of a
  demand with a shared result.
* A task that is not queued is read from its data source instead of being
  skipped.
* Queued records are removed from the queue after the task was processed.
* A task without data source or data target raises an
  `InvalidConfigurationException`.
* Records from an iterator are converted to an array; their keys are not
  kept. Sources that need keys must return arrays.
* A finisher cannot replace the result object.

**Migration:**

```php
// before (extension)
$processor->buildQueue($demand);
$records = $processor->process($demand); // array

// after (core)
$processor = new DataTransferProcessor();
$processor->buildQueue($demand);
$result = (new DemandProcessor($processor))->process($demand);
$records = $result->toArray();
```

Data sources that return objects which must be processed as arrays (e.g.
domain objects) have to convert them in the source, not in the processor.

### Factories moved into the core

Issue: [#23](https://github.com/CPS-IT/import-export-core/issues/23)

**Affects:** code that uses the extension's `FactoryFactory`,
`ComponentFactoryMap`, the component factories (`PreProcessorFactory` etc.),
`DataSourceFactory`, `DataTargetFactory`, `TransferTaskFactory` or
`TransferSetFactory`; configurations that rely on their behaviour.

**Change:** the core contains `ComponentRegistry`, one `ComponentFactory` for
all component roles, and `TransferTaskFactory`/`TransferSetFactory` in
`CPSIT\ImportExportCore\Factory\`. Differences:

* Component classes can be given as registered short name or as fully
  qualified class name. A missing `class` uses the default registered for the
  role; without default, an `InvalidConfigurationException` is raised.
* Configurable components always receive a configuration; a missing `config`
  is passed as `[]`. Components that do not accept an empty configuration
  raise an exception when they are created, not when they are used.
* A set that refers to an undefined task raises an
  `InvalidConfigurationException`. Before, the task was skipped.
* `TransferTaskFactory::create($identifier, $settings)` and
  `TransferSetFactory::create($identifier, $settings, $taskDefinitions)`
  replace `get($settings, $identifier)`. The set factory does not read the
  configuration handler; the caller passes the task definitions.
* Objects are created by an `InstantiatorInterface`. `ContainerInstantiator`
  clones the object returned by a PSR-11 container, like the extension cloned
  the result of `GeneralUtility::makeInstance()`.

**Migration:**

```php
// before (extension)
$set = $transferSetFactory->get($setSettings, 'legacyData');

// after (core)
$registry = new ComponentRegistry();
$registry->setDefault(DataSourceInterface::class, DataSourceDB::class);
$registry->setDefault(DataTargetInterface::class, DataTargetRepository::class);
$componentFactory = new ComponentFactory($registry, new ContainerInstantiator($container));
$setFactory = new TransferSetFactory(new TransferTaskFactory($componentFactory));

$configuration = $configurationHandler->getFullConfiguration();
$set = $setFactory->create('legacyData', $setSettings, $configuration['import']['tasks']);
```

Remove `set` entries for tasks that do not exist. Check components that are
configured without `config`: `isConfigurationValid([])` must be `true` for
them, or the configuration must be added.

### Pipeline contracts replace the component interfaces

Issue: [#26](https://github.com/CPS-IT/import-export-core/issues/26)

**Affects:** implementations of the component interfaces in
`CPSIT\ImportExportCore\Component\` (deprecated), code that reads the
components of a `TransferTask`, initializers that add records, finishers that
read records, post-processors that change the record before conversion.

**Change:** new interfaces in `CPSIT\ImportExportCore\Pipeline\`. Components
return values instead of changing arguments by reference. They receive their
configuration once, when they are created (`setConfiguration()`), not with
each call. The interfaces do not contain `isDisabled()`; conditions will be
handled by the processor
([#24](https://github.com/CPS-IT/import-export-core/issues/24)).

| Deprecated (`Component\`) | Pipeline (`Pipeline\`) |
|---|---|
| `process(array $configuration, array &$records): bool` | `InitializerInterface::initialize(TaskContext $context): void` |
| `process(array $configuration, array &$record): bool` | `PreProcessorInterface::process(array $record): array` |
| `convert(array &$record, array $configuration): mixed` | `ConverterInterface::convert(array $record): mixed` |
| `process(array $configuration, mixed &$convertedRecord, array &$record): bool` | `PostProcessorInterface::process(mixed $convertedRecord, array $record): mixed` |
| `process(array $configuration, array &$records, array\|object &$result): bool` | `FinisherInterface::finish(TaskContext $context): void` |

Initializers and finishers receive a `Pipeline\TaskContext` with the task
key, the task, the result, the ID map, the checkpoint store and the
checkpoint the run started from
([#41](https://github.com/CPS-IT/import-export-core/issues/41)). The result
is `$context->result`. An initializer that resets the target should check
`$context->isResumed()` first.

Components implementing the deprecated interfaces keep working: the
component factory and `TransferTask` wrap them in adapters. The adapters
pass the configuration with each call and delegate `isDisabled()`, as before.
Differences:

* Initializers receive an empty list of records; records they add are not
  processed. They do not receive the `TaskContext`, so they cannot tell
  whether a run resumes an interrupted one. Initializers that reset the
  target (e.g. truncate or delete from tables) would remove the records the
  interrupted run already wrote. Before using a checkpoint store, migrate
  them to `Pipeline\InitializerInterface` and skip the reset if
  `$context->isResumed()`.
* Finishers receive an empty list of records and the result of the
  `TaskContext`.
* Changes of post-processors to `$record` are discarded; changes to
  `$convertedRecord` are kept.
* `TransferTask::get*()` return the adapters. Use
  `LegacyAdapterInterface::getComponent()` to get the wrapped component.

**Migration:** implement the pipeline interface, read the configuration from
the component (e.g. `ConfigurableTrait`) and return the result:

```php
// before
final class SetTitle implements \CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface
{
    public function process(array $configuration, array &$record): bool
    {
        $record['title'] = $configuration['value'];
        return true;
    }
    // isDisabled(), isConfigurationValid(), setConfiguration(), getConfiguration()
}

// after
final class SetTitle implements \CPSIT\ImportExportCore\Pipeline\PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function process(array $record): array
    {
        $record['title'] = $this->configuration['value'];
        return $record;
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return isset($configuration['value']);
    }
}
```

An initializer that resets the target skips the reset when the run resumes:

```php
final class TruncateTables implements \CPSIT\ImportExportCore\Pipeline\InitializerInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function initialize(TaskContext $context): void
    {
        if ($context->isResumed()) {
            return;
        }
        // truncate the configured tables
    }
}
```

To keep the per-record `disabled` check until
[#24](https://github.com/CPS-IT/import-export-core/issues/24) is done,
implement `Component\DisableableInterface`.

### Queue moved into the core

Issue: [#40](https://github.com/CPS-IT/import-export-core/issues/40)

**Affects:** configurations using the extension's `DataSourceQueue` or
`DataTargetQueue`, code using `QueueItem` or `QueueItemRepository`, existing
items in the extension's queue table.

**Change:** `QueueItem`, `QueueRepositoryInterface` and an in-memory
repository are part of the core (`CPSIT\ImportExportCore\Queue\`), as are
`DataSourceQueue` and `DataTargetQueue`
(`CPSIT\ImportExportCore\Persistence\`). The extension provides the
repository for its database table. Differences:

* `DataSourceQueue` claims the items it reads (status processing). After the
  task, they are marked finished. Before, the status was never changed. If a
  task fails, its items stay in processing; `QueueRepositoryInterface::release()`
  sets them back to new.
* The payload is an array. Repositories store it as JSON. The checksum is
  `QueueItem::checksum($queue, $data)`.
* `DataSourceQueue`: the option `batchSize` is called `limit` (number of
  items per run). It is independent of the batch size of the processor.
* Queue identifiers are free strings; the prefix `import.` or `export.` is no
  longer required.
* `DataTargetQueue::persist()` raises an `UnexpectedValueException` for
  records that are not arrays.

**Migration:**

```yaml
# before
source:
  class: CPSIT\T3importExport\Persistence\DataSourceQueue
  config:
    identifier: import.news
    batchSize: 50

# after
source:
  class: CPSIT\ImportExportCore\Persistence\DataSourceQueue
  config:
    identifier: import.news
    limit: 50
```

Existing items in the queue table whose `data` is not a JSON object, or
whose checksum was computed the old way, have to be converted or enqueued
again.

### `DataStreamInterface` holds a stream

Issue: [#40](https://github.com/CPS-IT/import-export-core/issues/40)

**Affects:** implementations of `DataStreamInterface`, callers of
`setStreamBuffer()` and `getStreamBuffer()`.

**Change:** the output of an export is held in a PSR-7 `StreamInterface`
instead of a string. `DataStreamInterface` declares `getStream()` and
`setStream()`. `getStream()` never returns null; `DataStream` starts with an
empty `Stream\TemporaryStream`, which keeps up to 2 MB in memory and moves
larger content to a temporary file. `setStreamBuffer()` and
`getStreamBuffer()` are deprecated and work on the stream: they replace it
and read its whole content. `getStreamBuffer()` returns null for an empty
stream, also after `setStreamBuffer('')`.

**Migration:** implementations of `DataStreamInterface` must add
`getStream()` and `setStream()`; extending `DataStream` covers both. Callers
should write to and read from the stream instead of passing one string:

```php
// before
$dataStream->setStreamBuffer($xml);
file_put_contents($path, $dataStream->getStreamBuffer());

// after
$stream = $dataStream->getStream();
foreach ($chunks as $chunk) {
    $stream->write($chunk);
}

$stream->rewind();
$file = fopen($path, 'wb');
while (!$stream->eof()) {
    fwrite($file, $stream->read(8192));
}
fclose($file);
```
