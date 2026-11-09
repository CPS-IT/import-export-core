# Architecture

This document describes the purpose of the library, its current state and the
planned direction. It replaces the earlier planning documents
(`ImplementationPlan.md`, `ProposedInterfaceDesign.md`, `ComponentAnalysis.md`,
`Feature/YamlConfigurationLoader.md`); they remain available in the git history.

## Goal

`cpsit/import-export-core` provides the import and export functionality
independent of TYPO3. The TYPO3 extension
[Import & Export](https://github.com/dwenzel/t3import_export) (`t3import_export`)
consumes it and contains only CMS-specific classes: backend module, commands,
database and FAL access, Extbase property mapping, TypoScript.

Rules:

* The core does not depend on TYPO3 packages, and it does not encode TYPO3
  structures (TypoScript paths, TCA conventions, Extbase or FAL types), not
  even in docblocks.
* Dependencies point from the extension to the core, never back.
* Extension points are interfaces in the core. TYPO3-specific behaviour is
  added by implementing them in the extension (adapters).

## Processing model

An import or export is described by a *task*. Tasks can be grouped into *sets*.
A task is processed in this order:

1. The **data source** returns the records (`DataSourceInterface::getRecords()`).
2. **Initializers** run once before the records are processed.
3. For each record:
   1. **Pre-processors** modify the record array.
   2. **Converters** turn the record into the target representation.
   3. **Post-processors** modify the converted record.
   4. The **data target** persists it (`DataTargetInterface::persist()`).
4. The data target finishes (`persistAll()`).
5. **Finishers** run once after all records.

Components report messages (`MessageContainer`) and contribute to a result
(`ResultInterface`, implemented by `TaskResult`).

The orchestration is `Service\DataTransferProcessor`
(`TransferProcessorInterface`). It processes one task; `Service\DemandProcessor`
processes all tasks of a demand with a shared result. Records are read lazily
and processed in batches; batch aware targets are flushed after each batch,
and PSR-14 events (`TaskStarted`, `BatchProcessed`, `TaskFinished`) allow
progress reports and throttling. The result keeps counters
(`TransferStatistics`); collecting the converted records can be switched off.
Staged transfers use a queue (`Queue\`): `DataTargetQueue` writes records
into a `QueueRepositoryInterface`, `DataSourceQueue` reads and claims them and
marks them finished after the task. The core contains an in-memory repository;
the extension provides one for its database table. Stream-based data streams
follow ([#40](https://github.com/CPS-IT/import-export-core/issues/40)). The task and
set models (`TransferTask`, `TransferSet`) are part of the core.

`Dbal\DataSourceDBAL` and `Dbal\DataTargetDBAL` read from and write to
database tables via Doctrine DBAL, without TYPO3. They get their connections
by name from a `Dbal\ConnectionProviderInterface`, so a task can read from
one database and write to another. Conditions are configured as field/value
pairs and passed as parameters; there is no SQL in the configuration. The
source supports resuming with a cursor field; the target commits each batch
in one transaction and reports target IDs for the ID map. `doctrine/dbal` is
an optional dependency ([#42](https://github.com/CPS-IT/import-export-core/issues/42)).

`Factory\TransferSetFactory` and `Factory\TransferTaskFactory` build sets and
tasks from configuration. Components are built by one
`Factory\ComponentFactory` for all roles. It resolves the class with a
`ComponentRegistry` (short name or fully qualified class name, optional default
class per role) and creates the object with an `InstantiatorInterface`:
`Instantiator` uses `new`, `ContainerInstantiator` gets a clone from a PSR-11
container. The extension registers its components and defaults in the
registry ([#23](https://github.com/CPS-IT/import-export-core/issues/23)).

Components implement the pipeline contracts in `Pipeline\`: they return
values and receive their configuration once, when they are created.
Components implementing the deprecated interfaces in `Component\` are
wrapped in adapters (`Pipeline\Legacy\`) by the component factory and by
`TransferTask`, so the processor only works with the pipeline contracts
([#26](https://github.com/CPS-IT/import-export-core/issues/26)). Typed task
and component definitions are planned as a follow-up.

## Current state (0.1.0)

| Namespace (`CPSIT\ImportExportCore\`) | Content |
|---------------------------------------|---------|
| (root)              | `ConfigurableInterface`, `IdentifiableInterface`, `LoggingInterface`, `RenderContentInterface` and traits |
| `Component\`        | `ComponentInterface` and the interfaces for initializers, pre-processors, converters, post-processors, finishers |
| `Persistence\`      | `DataSourceInterface`, `DataTargetInterface` |
| `Domain\Model\`     | `TaskResult`, transfer task and set interfaces |
| `Messaging\`        | `Message`, `MessageContainer` and interfaces |
| `Configuration\`    | `ConfigurationHandler`, `YamlConfigurationLoader`, `YamlConfigurationProvider` |
| `Service\`          | `YamlConfigurationParser` |
| `Exception\`        | `ImportExportExceptionInterface` and exceptions |

The 0.1.0 interfaces are largely the contracts of the extension, moved into
the core. Before the release they were cleaned up (milestone
[0.1.0](https://github.com/CPS-IT/import-export-core/milestone/1)):

* a neutral configuration schema (`import|export` -> `tasks|sets`) instead of
  the TypoScript structure `module.tx_t3importexport.settings`; later
  configuration files replace values of earlier ones
* no TYPO3 references in code, docblocks or file headers; database-specific
  exceptions and constants moved to the extension
* one exception interface (`Exception\ImportExportExceptionInterface`);
  exceptions extend SPL exceptions
* native types on the public interfaces; PHPStan level 6
* fixes in `MessageContainerTrait`, `LoggingTrait` and `TaskResult`

While the version is `0.x`, a new minor version may change the interfaces.

## Known issues

Found in a review of the core and the TYPO3 13 branch of the extension. The
design issues are addressed in 0.2.0 (milestone
[0.2.0](https://github.com/CPS-IT/import-export-core/milestone/2)):

* The engine was not in the core, so the core could not run an import on its
  own. Done in 0.2.0
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22)).
* Components are instantiated by a hard-coded factory map and
  `GeneralUtility::makeInstance()`; third parties cannot register component
  types. Done in 0.2.0 ([#23](https://github.com/CPS-IT/import-export-core/issues/23))
* Every component inherits a TYPO3 frontend dependency: the extension's
  `AbstractComponent` evaluates `disabled` conditions with TypoScript. ([#24](https://github.com/CPS-IT/import-export-core/issues/24))
* There is no abstraction for paths and resources, so file-based sources and
  targets cannot move into the core. ([#25](https://github.com/CPS-IT/import-export-core/issues/25))
* Components modify arrays by reference and receive their configuration twice
  (per call and via setter). Done in 0.2.0 ([#26](https://github.com/CPS-IT/import-export-core/issues/26))
* Messages are collected through traits instead of injected collaborators.
  ([#27](https://github.com/CPS-IT/import-export-core/issues/27))
* Task and set models are mutable; `TaskResult` keeps iterator state. ([#28](https://github.com/CPS-IT/import-export-core/issues/28))

## Direction for 0.2.0

* Core interfaces for the result (`ResultInterface`) and the processor
  (`TransferProcessorInterface`); the processor, task and set models move into
  the core.
* A factory and registry layer based on PSR-11, so that component types can be
  registered by short name, including by third parties.
* A core `AbstractComponent` with a `ConditionEvaluatorInterface`; the
  TypoScript evaluator becomes an adapter in the extension. Generic
  pre-processors and validators move into the core.
* A path and resource abstraction; CSV and XML sources and targets move into
  the core. `EXT:` paths are resolved in the extension.
* New component contracts: return values instead of references, configuration
  given once, typed definitions. The extension keeps an adapter for components
  using the old signatures.

Classes that stay in the extension: backend module and commands, database
access (`DataSourceDB`, `DataTargetDB`, initializers working on tables),
Extbase repositories and property mapping, FAL (file references, `WriteFile`,
`MoveFile`), content rendering, translation, cache clearing.
