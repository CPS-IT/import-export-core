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
