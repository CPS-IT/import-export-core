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

### Changed

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
