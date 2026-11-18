# Processing tasks

## Building tasks from the configuration

```php
use CPSIT\ImportExportCore\Configuration\ConfigurationHandler;
use CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader;
use CPSIT\ImportExportCore\Factory\ComponentFactory;
use CPSIT\ImportExportCore\Factory\ComponentRegistry;
use CPSIT\ImportExportCore\Factory\ContainerInstantiator;
use CPSIT\ImportExportCore\Factory\TransferSetFactory;
use CPSIT\ImportExportCore\Factory\TransferTaskFactory;
use CPSIT\ImportExportCore\Service\YamlConfigurationParser;

$configuration = (new ConfigurationHandler())
    ->addConfiguration(new YamlConfigurationLoader(new YamlConfigurationParser()), 'config/import.yaml')
    ->getFullConfiguration();

$componentFactory = new ComponentFactory(new ComponentRegistry(), new ContainerInstantiator($container));
$taskFactory = new TransferTaskFactory($componentFactory);

$task = $taskFactory->create('pages', $configuration['import']['tasks']['pages']);
$set = (new TransferSetFactory($taskFactory))
    ->create('all', $configuration['import']['sets']['all'], $configuration['import']['tasks']);
```

See [Components](Components.md) for the registry and the instantiators.

## Running tasks

`DataTransferProcessor::process()` runs one task and returns a
`ResultInterface`. `DemandProcessor` runs all tasks of a demand
(`TaskDemand::setTasks()`) with a shared result.

```php
use CPSIT\ImportExportCore\Domain\Model\Dto\TaskDemand;
use CPSIT\ImportExportCore\Service\DataTransferProcessor;
use CPSIT\ImportExportCore\Service\DemandProcessor;

$processor = new DataTransferProcessor(
    batchSize: 500,
    collectRecords: false,
    eventDispatcher: $dispatcher,   // PSR-14
    idMap: $idMap,                  // see State.md
    checkpointStore: $checkpoints,  // see State.md
);

$demand = new TaskDemand();
$demand->setTasks($set->getTasks());
$result = (new DemandProcessor($processor))->process($demand);
```

| Argument | Default | Description |
|---|---|---|
| `batchSize` | 100 | records per batch |
| `collectRecords` | `true` | keep the converted records in the result (`ResultInterface::toArray()`). Set to `false` for large transfers. |
| `eventDispatcher` | `NullEventDispatcher` | PSR-14 dispatcher for the events below |
| `idMap` | `NullIdMap` | ID map, see [State](State.md) |
| `checkpointStore` | `NullCheckpointStore` | checkpoints for resuming, see [State](State.md) |
| `conditionEvaluator` | `ConditionEvaluator` | evaluates `disable`, see [Conditions](Conditions.md) |

## Order of processing

For each task:

1. `TaskStarted` is dispatched.
2. Initializers run (`InitializerInterface::initialize()`).
3. The records of the source are read lazily, unless they were read in
   advance (see below). In batches of `batchSize` records, for each record:
   1. filters (`FilterInterface::accepts()`) with the record as read
   2. pre-processors (`PreProcessorInterface::process()`)
   3. filters implementing `ProcessedRecordFilterInterface`
      (`acceptsProcessed()`)
   4. converters, each receiving the output of the previous one
   5. post-processors, receiving the converted record and the pre-processed
      record
   6. the target, if it is not disabled (`DataTargetInterface::persist()`)
4. After each batch: `BatchAwareInterface::flushBatch()` of the target, ID
   mappings of the batch, a checkpoint, `BatchProcessed`.
5. After the last batch: `persistAll()` of the target (only if records were
   read), then `CompletableSourceInterface::complete()` of the source.
6. Finishers run (`FinisherInterface::finish()`).
7. The checkpoint is cleared and `TaskFinished` is dispatched.

A record that a filter does not accept, or that a disabled target skips, is
counted as skipped. Records need to be arrays as soon as the task has filters,
pre-processors, converters, post-processors or ID map settings.

Records of several tasks can be read in advance with
`DataTransferProcessor::buildQueue($demand)`, e.g. to read all sources of a
set before any task writes. Queued records are held in memory.

## Result

`ResultInterface` holds:

* messages of the components (`getMessages()`, `hasMessageWithId()`), see
  [Components](Components.md#messages)
* the converted records, if `collectRecords` is `true` (`toArray()`)
* counters (`getStatistics()`): `getRead()`, `getPersisted()`,
  `getSkipped()`, `getBatches()`
* additional information set by components (`setInfo()`, `getInfo()`)

## Events

The processor dispatches these events (`CPSIT\ImportExportCore\Event\`)
through the PSR-14 dispatcher given to its constructor:

| Event | When | Properties |
|---|---|---|
| `TaskStarted` | before the initializers | `task`, `taskKey` |
| `BatchProcessed` | after a batch was persisted and flushed | `task`, `taskKey`, `batchNumber` (from 1 per task), `recordCount`, `result` |
| `TaskFinished` | after the finishers | `task`, `taskKey`, `recordCount` (records read), `result` |

The events are read-only. Listeners can report progress, write logs, or pause
between batches to limit the load on source and target.
`taskKey` is the task identifier, or `object:<id>` for tasks without one.
