# ID map, checkpoints and incremental mode

The processor keeps two kinds of state across runs: the ID map (which source
record became which target record) and checkpoints (how far an interrupted
task got). Both are passed to the processor's constructor; without them, the
null implementations are used and nothing is kept.

| Interface | Implementations |
|---|---|
| `State\IdMapInterface` | `NullIdMap` (default), `InMemoryIdMap`, `Dbal\State\DbalIdMap` |
| `State\CheckpointStoreInterface` | `NullCheckpointStore` (default), `InMemoryCheckpointStore`, `Dbal\State\DbalCheckpointStore` |

The DBAL implementations need their tables, see
[Persistence](Persistence.md#tables-of-the-core).

## ID map

With the task option `idMap`, the processor records an `IdMapping` for each
written record:

```yaml
idMap:
  namespace: pages        # e.g. a table or entity type
  sourceIdField: uid      # field of the source record holding its ID
  targetIdField: uid      # optional: field for the target ID, if it differs
```

* `namespace` and `sourceId` identify the mapping.
* `targetId` comes from `IdentifyingTargetInterface::getTargetId()`; a task
  with `idMap` and a target without this interface raises an
  `InvalidConfigurationException`.
* `checksum` is `IdMapping::recordChecksum($record, $sourceIdField,
  $targetIdField)` of the record after the pre-processors, without its ID
  fields.

A source record without a value in `sourceIdField` raises an
`UnexpectedValueException`. With a batch aware target, the mappings of a
batch are recorded after `flushBatch()`, so a record cannot refer to another
record of the same batch through the ID map. Use a batch size of 1 or a
second task for such references, e.g. the parent page in a page tree.

Source IDs are compared exactly: `ABC`, `abc` and `abc ` are different
source IDs. `DbalIdMap` gives its key columns a binary collation on MySQL and
MariaDB for this, see [Persistence](Persistence.md#tables-of-the-core).

`IdMapInterface::get()` returns a `NullIdMapping` (`exists()` is false) if
there is no mapping.

Components using the ID map:

| Component | Purpose |
|---|---|
| `TargetIdPreProcessor` | upsert: sets the target ID of a record written in an earlier run, so the target updates it instead of inserting it again |
| `ResolveReferencePreProcessor` | replaces source IDs in a field with target IDs, e.g. the parent page or the categories of a record |
| `UnchangedRecordFilter` | skips records whose checksum did not change since they were written |

Options: [Configuration/Components.md](../Configuration/Components.md).

## Incremental mode

A task that runs repeatedly and writes only new and changed records:

```yaml
idMap:
  namespace: news
  sourceIdField: uid
preProcessors:
  10:
    class: CPSIT\ImportExportCore\State\Component\TargetIdPreProcessor
    config:
      namespace: news
      sourceIdField: uid
      targetIdField: uid
filters:
  10:
    class: CPSIT\ImportExportCore\State\Component\UnchangedRecordFilter
    config:
      namespace: news
      sourceIdField: uid
target:
  class: CPSIT\ImportExportCore\Dbal\DataTargetDBAL
  config:
    table: tx_news
    key: [uid]
    idField: uid
```

If the target ID is set in another field than the source ID, e.g. records
of a file identified by `email` and written to a table with `uid`, set
`targetIdField` in `idMap`, `TargetIdPreProcessor` and `UnchangedRecordFilter`.
The field is missing in the first run and set from the second run on, so it
is left out of the checksum; without the option, each record is written once
more in the second run.

`UnchangedRecordFilter` runs after the pre-processors, so values that
pre-processors add (relations, resolved references) are part of the checksum.
Changes of converters or post-processors are not detected: after changing
them, run the task once without the filter.

## Checkpoints

A `Checkpoint` holds the position of a task after its last completed batch:
batch number, number of processed records, and the cursor of a resumable
source.

* After each batch, the processor saves a checkpoint, if the target is batch
  aware (`BatchAwareInterface`). Other targets may hold records until
  `persistAll()`, so their progress is not known.
* When the task completed, its checkpoint is cleared.
* When a task starts with a checkpoint, it resumes: a
  `ResumableSourceInterface` source continues from the stored cursor; other
  sources are read from the beginning, and the records already processed are
  skipped.

Initializers and finishers get the checkpoint the run started from in the
`TaskContext` (`$context->resumedFrom`). An initializer that resets the target,
e.g. truncates a table, must not do so when the run resumes:

```php
public function initialize(TaskContext $context): void
{
    if ($context->isResumed()) {
        return;
    }
    // reset the target
}
```

Checkpoints are stored per task key (the task identifier). The core does not
lock tasks; the application has to prevent two runs of the same task at the
same time.
