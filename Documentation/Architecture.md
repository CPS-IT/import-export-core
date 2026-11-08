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

Components report messages (`MessageContainer`) and contribute to a
`TaskResult`.

Today this orchestration (`DataTransferProcessor`), the task and set models and
the factories are part of the extension. Moving them into the core is planned
for 0.2.0 (see below).

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
| `Exception\`        | exceptions |

The 0.1.0 interfaces are largely the contracts of the extension, moved into
the core. They are released as they are, after the cleanup listed below, so
that the extension can depend on a tagged version. While the version is `0.x`,
a new minor version may change them.

## Known issues

Found in a review of the core (`feature/yamlConfiguration`) and the TYPO3 13
branch of the extension (`feature/v13compatible`).

To be fixed before 0.1.0 (milestone
[0.1.0](https://github.com/CPS-IT/import-export-core/milestone/1)):

* The configuration loader and `ConfigurationHandler` return and read the
  TypoScript structure `module.tx_t3importexport.settings`. The extension
  partly reads `['settings']` instead. ([#15](https://github.com/CPS-IT/import-export-core/issues/15))
* Several configuration files are merged with `array_merge_recursive()`, which
  turns repeated scalar keys into arrays. ([#16](https://github.com/CPS-IT/import-export-core/issues/16))
* Docblocks and file headers refer to TYPO3. ([#17](https://github.com/CPS-IT/import-export-core/issues/17))
* `MessageContainerTrait` declares a constructor; `LoggingTrait` fails if the
  using class does not define `ERROR_CODES`; `TaskResult` is not `Countable`.
  ([#18](https://github.com/CPS-IT/import-export-core/issues/18))
* Public interfaces lack native types; PHPStan runs at level 3. ([#19](https://github.com/CPS-IT/import-export-core/issues/19))
* There is no common exception type; database-specific exceptions and
  constants (`KEY_TABLE`, `KEY_WHERE`, ...) are part of the core. ([#20](https://github.com/CPS-IT/import-export-core/issues/20))
* `Resources/config/services.yaml` and the TYPO3 development dependencies.
  ([#21](https://github.com/CPS-IT/import-export-core/issues/21))

Design issues addressed in 0.2.0 (milestone
[0.2.0](https://github.com/CPS-IT/import-export-core/milestone/2)):

* The engine is not in the core, so the core cannot run an import on its own.
  ([#22](https://github.com/CPS-IT/import-export-core/issues/22))
* Components are instantiated by a hard-coded factory map and
  `GeneralUtility::makeInstance()`; third parties cannot register component
  types. ([#23](https://github.com/CPS-IT/import-export-core/issues/23))
* Every component inherits a TYPO3 frontend dependency: the extension's
  `AbstractComponent` evaluates `disabled` conditions with TypoScript. ([#24](https://github.com/CPS-IT/import-export-core/issues/24))
* There is no abstraction for paths and resources, so file-based sources and
  targets cannot move into the core. ([#25](https://github.com/CPS-IT/import-export-core/issues/25))
* Components modify arrays by reference and receive their configuration twice
  (per call and via setter). ([#26](https://github.com/CPS-IT/import-export-core/issues/26))
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
