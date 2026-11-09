# Changelog

All notable changes to this project are documented in this file. The project
follows [Semantic Versioning](https://semver.org/). While the version is `0.x`,
a new minor version may contain breaking changes.

## Unreleased

### Added

* `ResultInterface`, implemented by `TaskResult`
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* `TransferProcessorInterface` and `DataTransferProcessor`: the processor
  moved from the extension into the core and depends on core interfaces only.
  It processes a single task; records can still be read in advance for
  several tasks with `buildQueue()`
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* `DemandProcessor`: processes all tasks of a demand with a shared result
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* `TransferTask`, `TransferSet`, `DataStream`, `DataStreamInterface`,
  `DemandInterface` and `TaskDemand`, moved from the extension
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* `DisableableInterface` for data targets that can be disabled per record
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* Factory and registry layer: `ComponentRegistry` (short names and default
  classes per component role), `ComponentFactory` (one factory for all
  roles), `Instantiator` and `ContainerInstantiator` (PSR-11),
  `TransferTaskFactory` and `TransferSetFactory`. New dependency
  `psr/container`
  ([#23](https://github.com/CPS-IT/import-export-core/issues/23))
* Pipeline contracts in `CPSIT\ImportExportCore\Pipeline\`: components return
  values instead of changing arguments by reference and receive their
  configuration once, when they are created. Components implementing the
  previous interfaces are wrapped in adapters
  ([#26](https://github.com/CPS-IT/import-export-core/issues/26))

* Batch processing: `DataTransferProcessor` reads records lazily and
  processes them in batches (`$batchSize`, default 100). Data targets
  implementing `BatchAwareInterface` are flushed after each batch.
  `persistAll()` is still called once per task
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))
* `TransferStatistics` (records read, persisted, skipped; batches), available
  through `ResultInterface::getStatistics()`. Collecting the converted records
  in the result can be switched off (`$collectRecords`); the counters are kept
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))
* PSR-14 events `TaskStarted`, `BatchProcessed` and `TaskFinished`, dispatched
  through an optional event dispatcher, e.g. for progress reports or to pause
  between batches. New dependency `psr/event-dispatcher`
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))

* Queue for staged transfers: `Queue\QueueItem`, `Queue\QueueRepositoryInterface`
  and `Queue\InMemoryQueueRepository`; `Persistence\DataTargetQueue` writes
  records into a queue, `Persistence\DataSourceQueue` reads and claims them.
  Claimed items are marked finished after the task
  (`CompletableSourceInterface`)
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))
* `DataStreamInterface::getStream()` and `setStream()`: the output of an
  export is held in a PSR-7 stream, so it can be written and read in chunks.
  `Stream\TemporaryStream` (backed by `php://temp`) is the default stream of
  `DataStream`. New dependency `psr/http-message`
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))
* State store in `CPSIT\ImportExportCore\State\`: `IdMapInterface` maps source
  IDs to target IDs per namespace (`IdMapping`, `NullIdMapping` for a missing
  mapping); `CheckpointStoreInterface` stores the position of a task
  (`Checkpoint`). In-memory and null implementations
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* `Pipeline\TaskContext`, passed to initializers and finishers: task key,
  task, result, ID map, checkpoint store and the checkpoint the run started
  from ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* Resuming interrupted tasks: `DataTransferProcessor` accepts an ID map and a
  checkpoint store. With a batch aware target, it saves a checkpoint after
  each batch and clears it when the task completed. A task with a checkpoint
  continues after it; sources implementing the new
  `Persistence\ResumableSourceInterface` continue from their cursor, other
  sources are read again and the processed records are skipped
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* Filters (`Pipeline\FilterInterface`, task key `filters`): run before the
  pre-processors on the record as read from the source; records they do not
  accept are skipped
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* Recording target IDs: with ID map settings on the task (`idMap: {namespace,
  sourceIdField}`, `State\IdMapSettings`), the processor records source ID,
  target ID and the checksum of the source record (`IdMapping::checksum()`)
  once the records are written: after `flushBatch()` for batch aware targets,
  after `persist()` for others. The target must implement the new
  `Persistence\IdentifyingTargetInterface` (`getTargetId($record)`)
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* `State\Component\ResolveReferencePreProcessor`: replaces source IDs in a
  field with target IDs from the ID map (single IDs, lists, separated
  strings); option `onMissing` (`fail`, `keep`, `empty`)
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))
* `State\Component\UnchangedRecordFilter`: incremental mode, skips records
  whose checksum matches their mapping
  ([#41](https://github.com/CPS-IT/import-export-core/issues/41))

### Deprecated

* The component interfaces in `CPSIT\ImportExportCore\Component\`
  (`InitializerInterface`, `PreProcessorInterface`, `ConverterInterface`,
  `PostProcessorInterface`, `FinisherInterface`). Implement the interfaces in
  `CPSIT\ImportExportCore\Pipeline\` instead
  ([#26](https://github.com/CPS-IT/import-export-core/issues/26))
* `DataStreamInterface::setStreamBuffer()` and `getStreamBuffer()`. Use
  `setStream()` and `getStream()` instead
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))

### Changed

Migration of breaking changes is described in [UPGRADE.md](UPGRADE.md).

* Breaking: `isDisabled()` of the component interfaces accepts
  `?ResultInterface` instead of `?TaskResult`. Implementations must change
  the parameter type
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* Compared to the processor in the extension:
  * `process()` takes one task and returns a `ResultInterface` instead of an
    array; use `DemandProcessor` for several tasks
  * a task that is not queued is read from its data source instead of being
    skipped
  * queued records are removed from the queue after the task was processed
  * a task without data source or data target raises an
    `InvalidConfigurationException`
  * records from a generator are accepted
  * a finisher cannot replace the result object
  * the target is checked with `DisableableInterface` instead of the
    extension's `AbstractComponent`
* Compared to the factories in the extension
  ([#23](https://github.com/CPS-IT/import-export-core/issues/23)):
  * configurable components always receive a configuration; without `config`
    it is `[]`. Before, a data source without `config` raised an exception and
    other components were not configured
  * the configuration is set once by the component factory
  * a set that refers to an undefined task raises an
    `InvalidConfigurationException` instead of skipping the task
  * `TransferSetFactory::create()` receives the task definitions instead of
    reading them from the configuration handler
  * there is no fallback to `NullComponent` and no check of `object.class` in
    target settings
* Breaking: the processor works with the pipeline contracts
  ([#26](https://github.com/CPS-IT/import-export-core/issues/26)):
  * initializers do not receive records and cannot add records
  * finishers receive the result only, no records
  * changes of post-processors to the record before conversion are discarded
  * `TransferTask` wraps components implementing the deprecated interfaces in
    adapters; its getters return the pipeline interfaces
  * components are skipped per record only if they implement
    `DisableableInterface`; the adapters do
  * records must be arrays if the task has pre-processors, converters or
    post-processors
* Compared to the queue in the extension
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40)):
  * items are marked processing when read and finished after the task;
    before, their status was never changed and they were read again on every
    run
  * the payload is an array (stored as JSON by repositories), not a string;
    the checksum is computed from queue identifier and JSON
  * `DataSourceQueue` option `batchSize` is called `limit`
  * queue identifiers no longer need to start with `import.` or `export.`
  * `DataTargetQueue` raises an exception for records that are not arrays
    instead of returning `false`
* `buildQueue()` reads records of generators in advance. Before, the
  generator was stored and read only when the task was processed
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))
* Breaking: `DataStreamInterface` declares `getStream()` and `setStream()`.
  `DataStream` keeps its content in a stream; `getStreamBuffer()` returns
  null for an empty stream, also after `setStreamBuffer('')`
  ([#40](https://github.com/CPS-IT/import-export-core/issues/40))

## 0.1.0 - 2026-11-08

First release. The library contains the parts of the TYPO3 extension
[Import & Export](https://github.com/dwenzel/t3import_export) that do not
depend on TYPO3.

### Added

* component interfaces for initializers, pre-processors, converters,
  post-processors and finishers
* data source and data target interfaces
* messaging (`Message`, `MessageContainer`), `TaskResult`, transfer task and
  set interfaces
* configurable, identifiable and logging interfaces and traits
* YAML configuration: parser, loader, `ConfigurationHandler` and
  `YamlConfigurationProvider` for loading all files of a directory
* exception interface `ImportExportExceptionInterface`; all exceptions extend
  SPL exceptions
* documentation: README, CONTRIBUTING (branching model, release process),
  architecture overview

### Changed (compared to the development state used by the extension)

* configuration uses a neutral schema (`import|export` -> `tasks|sets`)
  instead of `module.tx_t3importexport.settings`
  ([#15](https://github.com/CPS-IT/import-export-core/issues/15))
* later configuration files replace values of earlier ones instead of being
  merged with `array_merge_recursive()`
  ([#16](https://github.com/CPS-IT/import-export-core/issues/16))
* native types on public interfaces; PHPStan level 6
  ([#19](https://github.com/CPS-IT/import-export-core/issues/19))

### Removed

* `MissingDatabaseException`, `InvalidColumnMapException` and the constants
  `ConfigurableInterface::KEY_TABLE`, `KEY_WHERE`, `KEY_SET_FIELDS`,
  `KEY_TYPES` (database-specific, moved to the extension)
  ([#20](https://github.com/CPS-IT/import-export-core/issues/20))
* `Resources/config/services.yaml` and TYPO3 development dependencies
  ([#21](https://github.com/CPS-IT/import-export-core/issues/21))

### Fixed

* `MessageContainerTrait` no longer declares a constructor; `LoggingTrait`
  works without `ERROR_CODES`; `TaskResult` implements `Countable`
  ([#18](https://github.com/CPS-IT/import-export-core/issues/18))
