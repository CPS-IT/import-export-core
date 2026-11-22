# Changelog

All notable changes to this project are documented in this file. The project
follows [Semantic Versioning](https://semver.org/). While the version is `0.x`,
a new minor version may contain breaking changes.

## Unreleased

### Added

* `Processor\ValidateXMLPreProcessor`, `Processor\XMLMapperPreProcessor`
  and `File\ValidateXMLFinisher`, moved from `ValidateXML` and `XMLMapper`
  of t3import_export. Schemas are loaded with the resource loader; their
  files must have the type `xsd`
  ([#25](https://github.com/CPS-IT/import-export-core/issues/25))

## 0.5.0 - 2026-11-20

### Added

* `Resource\FileTypePolicy`: `DataSourceCSV`, `DataSourceXML` and
  `DataTargetCSV` accept only their file types in `file` (`csv`, `tsv`, `txt`
  or `xml`); the application can set other types
* `HttpResourceLoader` takes a list of allowed hosts

* `Persistence\EmptyRunAwareInterface`: targets that have to act when a
  task reads no records

### Changed

* `PathResolver` and the file components reject executable file names, e.g.
  `.php`, also as `a.php.csv`

### Fixed

* `DataTargetCSV` kept the file of the previous run if a task read no
  records, so an export that should be empty showed the old rows. It now
  writes an empty file (the header row with `fields`), also if all records
  were filtered
  ([#93](https://github.com/CPS-IT/import-export-core/issues/93))

## 0.4.0 - 2026-11-19

### Fixed

* Incremental mode wrote each record once more in its second run if the
  target ID is in another field than the source ID. `idMap` and
  `UnchangedRecordFilter` take the option `targetIdField`, which is left out
  of the checksum like the source ID field
  ([#85](https://github.com/CPS-IT/import-export-core/issues/85))
* Expressions ([#86](https://github.com/CPS-IT/import-export-core/issues/86)):
  * in conditions, `result` is a read-only `ResultView`, so an expression
    cannot change the result of a task
  * errors during the evaluation raise an `InvalidConfigurationException`
    (`ERROR_EVALUATION_FAILED`) instead of the exception of the
    ExpressionLanguage
  * a missing array key evaluates to null without a PHP warning; `upper`,
    `lower`, `trim` and `strtotime` accept null without a deprecation
  * ranges have at most 10000 elements (`ERROR_RANGE_TOO_LARGE`)

## 0.3.0 - 2026-11-19

### Added

* `ResourceLoaderInterface` with `FileResourceLoader`, `HttpResourceLoader`
  and `DelegatingResourceLoader`, and `PathResolverInterface` with
  `PathResolver`, which does not allow paths outside of its base directory
  ([#25](https://github.com/CPS-IT/import-export-core/issues/25))
* `DataSourceCSV` and `DataSourceXML`: read files row by row or element by
  element and can resume; `DataTargetCSV`. They replace the components of the
  same name in the extension
  ([#25](https://github.com/CPS-IT/import-export-core/issues/25))
* `NullDataSource` and `NullDataTarget`
  ([#25](https://github.com/CPS-IT/import-export-core/issues/25))
* `ResourceStream`: PSR-7 stream around a PHP stream resource;
  `TemporaryStream` extends it
* The processor moves messages of sources and targets into the result, after
  each batch and at the end of a task

## 0.2.0 - 2026-11-18

### Added

* Documentation index and decision record for the documentation; the
  configuration reference of the components is generated from their
  docblocks (`composer docs:reference`) and checked by `composer lint`
  ([#76](https://github.com/CPS-IT/import-export-core/issues/76))
* API reference generated with phpDocumentor and published with GitHub Pages
  ([#76](https://github.com/CPS-IT/import-export-core/issues/76))
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
* Database source and target based on Doctrine DBAL (`Dbal\`), usable
  without TYPO3. `DataSourceDBAL` reads a table with fields, equality
  conditions (`where`), order and limit; with `cursorField` it reads in pages
  (keyset pagination) and resumes after the last processed record.
  `DataTargetDBAL` inserts records or updates the row with the same `key`,
  writes each batch in one transaction and reports target IDs for the ID map.
  Connections come from a `ConnectionProviderInterface` by name
  (`ConnectionRegistry`). `doctrine/dbal` (^4.3) is a suggested dependency
  ([#42](https://github.com/CPS-IT/import-export-core/issues/42))
* DBAL implementations of the state stores and the queue repository:
  `Dbal\State\DbalIdMap`, `Dbal\State\DbalCheckpointStore` and
  `Dbal\Queue\DbalQueueRepository` (table names configurable; concurrent
  runners claim each queue item once). `Dbal\Schema\SchemaInstaller`
  creates their tables and adds missing columns; it never changes or drops
  anything. Table definitions are available via `TableProviderInterface`
  ([#42](https://github.com/CPS-IT/import-export-core/issues/42))
* Upsert by ID map: `State\Component\TargetIdPreProcessor` sets the target
  ID from the ID map, or removes the ID field if the record has no mapping
  yet. `DataTargetDBAL` inserts records that have none of the `key` fields.
  A second run then updates the rows of the first run without extra columns
  such as `import_id` in the target table
  ([#56](https://github.com/CPS-IT/import-export-core/issues/56))
* `Processor\SetValuesPreProcessor` sets fields to constant values, and
  `Dbal\RelationListPreProcessor` sets the IDs related to a record from a
  relation (MM) table, e.g. to map them with `ResolveReferencePreProcessor`
  ([#58](https://github.com/CPS-IT/import-export-core/issues/58))
* `Pipeline\ProcessedRecordFilterInterface`: filters that run after the
  pre-processors and receive the pre-processed and the source record.
  `IdMapping::recordChecksum()`
  ([#62](https://github.com/CPS-IT/import-export-core/issues/62))
* `Processor\ValueRulesPreProcessor` applies declarative rules in order:
  fallback, default, copy, rename, map, remove, trim and join, each with an
  optional condition (`equals`, `in`, `empty`). No expressions are evaluated
  ([#64](https://github.com/CPS-IT/import-export-core/issues/64))
* `DataSourceDBAL` option `filter`: comparisons, NULL checks, `all`/`any`/`not`
  groups, relative dates (`{date: "-P2Y", from: "today"}`) and
  `exists`/`notExists` on a related table, as parameterized SQL
  (`Dbal\Filter`). Option `timezone` for the relative dates (default: PHP's
  default time zone); optional clock as second constructor argument
  ([#66](https://github.com/CPS-IT/import-export-core/issues/66))
* Pre-processors moved from t3import_export, with the same configuration and
  the test cases of the extension: `Processor\AddArraysPreProcessor`,
  `CleanPreProcessor`, `ImplodeArrayPreProcessor`, `StringToTimePreProcessor`
  and `UnsetEmptyFieldsPreProcessor`. Field lists may be a list or a
  comma-separated string. Differences to the extension: unknown options and
  incomplete `str_replace` options of `Clean` are rejected, `false` disables a
  `Clean` option, and a target value of `AddArrays` that is no array becomes the
  first element instead of causing an error
  ([#68](https://github.com/CPS-IT/import-export-core/issues/68))
* `ValueRulesPreProcessor`: rule `wrap` (`prefix`/`suffix` around a non-empty
  value) and paths for `remove` (`address.street`, `children.*.hidden`). They
  replace `ConcatenateFields.wrap`/`noTrimWrap` and `RemoveFields.children` of
  t3import_export. Unlike `ConcatenateFields`, `'0'` is not empty and is
  wrapped
  ([#69](https://github.com/CPS-IT/import-export-core/issues/69))
* Conditions: `Condition\ConditionEvaluator` evaluates the option `disable`
  of components (`true`/`1`, `if.result.hasMessage` with message IDs, or
  `expression`). `DataTransferProcessor` applies it to configurable components
  that do not implement `DisableableInterface`, so the core components can be
  disabled like the components of t3import_export. A custom evaluator can be
  passed to the processor (`$conditionEvaluator`)
  ([#24](https://github.com/CPS-IT/import-export-core/issues/24))
* `Expression\ExpressionEvaluator` (Symfony ExpressionLanguage with the
  functions `upper`, `lower`, `trim`, `date` and `strtotime`) and
  `Processor\EvaluateExpressionsPreProcessor`, moved from t3import_export.
  New dependency `symfony/expression-language`
  ([#24](https://github.com/CPS-IT/import-export-core/issues/24))

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
* Breaking: `UnchangedRecordFilter` runs after the pre-processors, so changes
  in values they add (relation lists, resolved references) are detected. The
  ID map stores the checksum of the pre-processed record without its ID
  field instead of the checksum of the source record; the first run after the
  upgrade writes all records once. `DataTransferProcessor::recordMapping()`
  has a new parameter `$processedRecord`
  ([#62](https://github.com/CPS-IT/import-export-core/issues/62))

### Removed

* `RenderContentInterface`: rendering of TypoScript content objects belongs to
  TYPO3, and the core does not use it
  ([#70](https://github.com/CPS-IT/import-export-core/issues/70))

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
