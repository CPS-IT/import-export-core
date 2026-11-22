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

## Unreleased

### Failed tasks tell the target before the source

Issue: [#109](https://github.com/CPS-IT/import-export-core/issues/109)

**Affects**: sources and targets implementing
`Persistence\FailureAwareInterface` that depend on the order in which
`fail()` is called.

**Change**: if a task fails, `DataTransferProcessor` calls `fail()` first on
the target, then on the source. Before, the source was told first. The new
order matches the order of `persistAll()` and `complete()`: the target rolls
back its transaction before the source updates its own state, e.g. queue
items on the same connection.

**Migration**: a source whose `fail()` expects the target to be still
unchanged, or a target whose `fail()` relies on the source having been told
first, has to handle this itself. No action is needed for `DataSourceQueue`
and `DataTargetDBAL`.

## 0.6.0

### Components of a task are sorted by their keys

Issue: [#81](https://github.com/CPS-IT/import-export-core/issues/81)

**Affects**: task configurations whose component lists (`filters`,
`initializers`, `preProcessors`, `converters`, `postProcessors`, `finishers`)
have integer keys that are not in ascending order, including keys that a later
configuration file adds between existing ones.

**Change**: `TransferTaskFactory` sorts the components of a list by their
integer keys in ascending order. Components with string keys (e.g. `last` or
`1.5`) follow in the order in which they appear in the configuration. Lists
without keys (`- class: ...`) keep their order. Before, components ran in the
order of the merged configuration, and a component added under a new key ran
last.

Note that `ConfigurationHandler` treats a component map whose keys are
exactly `0` to `n-1` (e.g. only `0: ...`) as a list: a later file with such a
map replaces the earlier one instead of merging into it, and a later map does
not merge into an earlier list either.

**Migration**: check lists with unordered keys and change the keys so that
they reflect the intended order. Use keys that do not start at `0` (`10`,
`20`, ...), so that later files can add components in between:

```yaml
preProcessors:
  10:
    class: ...   # runs first
  20:
    class: ...   # runs second, also if it appears first in the file
```

### `DataTargetDBAL` without `quoteColumns()` and `types()`

Issue: [#98](https://github.com/CPS-IT/import-export-core/issues/98)

**Affects**: subclasses of `Dbal\DataTargetDBAL` that call or override the
protected methods `quoteColumns()` or `types()`.

**Change**: the methods are removed. Rows are written with a query builder
that quotes the column names and sets the parameter types itself.

**Migration**: drop the overrides, or move the code into the subclass
(quote names with `Connection::quoteSingleIdentifier()`, pass
`Doctrine\DBAL\ParameterType` values as types).

### Failed tasks roll back `DataTargetDBAL` and fail queue items

Issue: [#102](https://github.com/CPS-IT/import-export-core/issues/102)

**Affects**: applications that handle a failed task with `DataTargetDBAL`
or `DataSourceQueue` themselves.

**Change**: if a task fails, `DataTransferProcessor` calls `fail()` on its
source and target (`Persistence\FailureAwareInterface`) before it passes
the exception on. An exception thrown by `fail()` is only reported in the
result (`DataTransferProcessor::ERROR_FAIL_THREW`); callers that do not
inspect the result do not see it. `DataTargetDBAL` rolls back the open
transaction of the current batch. `DataSourceQueue` marks all items claimed
in the run as failed; before, they stayed in processing until `release()`
was called.
This includes items the task has not read yet and items of batches that a
batch aware target already committed before the failure.

**Migration**: do not roll back the target connection after a failed task;
the transaction is no longer active, and `rollBack()` throws. To process
failed queue items again, enqueue their data again with `$requeue = true`
instead of calling `release()`. Targets that write the records of a queue
should be idempotent (e.g. `DataTargetDBAL` with `key`), since items of
committed batches are processed again.

## 0.5.0

### File components accept only their file types

**Affects**: tasks whose `file` of `DataSourceCSV`, `DataTargetCSV` or
`DataSourceXML` has another extension than `csv`, `tsv`, `txt` (CSV) or
`xml` (XML); configurations with executable file names in `file`.

**Change**: such files throw an `InvalidConfigurationException`
(`FileTypePolicy::ERROR_NOT_ALLOWED`). `PathResolver` rejects executable file
names, e.g. `a.php` or `a.php.csv`.

**Migration**: rename the files, or pass the types as constructor argument:
`new DataSourceCSV($loader, new FileTypePolicy(['csv', 'dat']))`.

### `DataTargetCSV` replaces the file also without records

Issue: [#93](https://github.com/CPS-IT/import-export-core/issues/93)

**Affects**: tasks with `DataTargetCSV` that read or write no records.

**Change**: the file is replaced with an empty one (only the header row with
`fields`, the byte order mark with `bom`) instead of keeping the file of the
previous run. The processor calls `persistEmptyRun()` of targets that
implement `Persistence\EmptyRunAwareInterface` if the source returned no
records.

**Migration**: none, unless a later step relied on the old file being kept.

## 0.4.0

### Expressions: read-only `result`, errors and ranges

Issue: [#86](https://github.com/CPS-IT/import-export-core/issues/86)

**Affects**: `disable` expressions that use `result`; callers of
`ExpressionEvaluator` that catch exceptions of the Symfony ExpressionLanguage;
expressions with ranges of more than 10000 elements.

**Change**:

* `ConditionEvaluator` passes `result` as `Condition\ResultView`, which
  provides `hasMessageWithId($id)`, `getMessageIds()`, `getInfo()` (scalar
  values only), `getRead()`, `getPersisted()` and `getSkipped()`. Methods that
  change the result, and `getStatistics()` and `getMessages()`, are not
  available.
* Errors while evaluating an expression raise an
  `InvalidConfigurationException` with the code
  `ExpressionEvaluator::ERROR_EVALUATION_FAILED`; the original exception is
  the previous one.
* Ranges with more than 10000 elements raise an
  `InvalidConfigurationException` (`ERROR_RANGE_TOO_LARGE`).

**Migration**:

| Before | After |
|---|---|
| `result.getStatistics().getRead()` | `result.getRead()` |
| `result.getMessages()` | `result.getMessageIds()` |

For larger ranges, create the evaluator with
`new ExpressionEvaluator(maximumRangeSize: 50000)`.

## 0.2.0

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
each call. The interfaces do not contain `isDisabled()`; the processor
evaluates the option `disable` of the configuration
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

Components using the pipeline interfaces are disabled with the option
`disable` in their configuration (`true`, `expression` or
`if.result.hasMessage`), evaluated by the processor's
`ConditionEvaluatorInterface`. A component that has to decide itself, e.g.
with the deprecated option `disabled`, implements
`Component\DisableableInterface`.

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

### `UnchangedRecordFilter` compares the pre-processed record

Issue: [#62](https://github.com/CPS-IT/import-export-core/issues/62)

**Affects:** tasks with `UnchangedRecordFilter` and ID map settings,
subclasses of `DataTransferProcessor` that override or call
`recordMapping()`, code that compares ID map checksums with
`IdMapping::checksum()`.

**Change:** filters implementing the new `ProcessedRecordFilterInterface` run
after the pre-processors; `UnchangedRecordFilter` is one of them. The
processor stores the checksum of the pre-processed record without its ID
field (`IdMapping::recordChecksum($record, $sourceIdField)`) instead of
`IdMapping::checksum($sourceRecord)`. `recordMapping()` receives the
pre-processed record as second parameter.

**Migration:** nothing to configure. Checksums stored before the upgrade do
not match, so the first run writes all records once and stores the new
checksums. Subclasses pass the pre-processed record:

```php
// before
$this->recordMapping($sourceRecord, $persistedRecord, $target, $settings, $key);

// after
$this->recordMapping($sourceRecord, $processedRecord, $persistedRecord, $target, $settings, $key);
```

Code that compares checksums uses `IdMapping::recordChecksum()` with the
pre-processed record and the source ID field.

### `RenderContentInterface` removed

Issue: [#70](https://github.com/CPS-IT/import-export-core/issues/70)

**Affects:** classes implementing `CPSIT\ImportExportCore\RenderContentInterface`.

**Change:** the interface is removed. The core does not render content;
t3import_export removes TypoScript content rendering in its next major version
(dwenzel/t3import_export#75).

**Migration:** remove the interface from the class, or declare an own interface
in the package that renders content.
