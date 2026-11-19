# Sources, targets and queue

## Data sources

`Persistence\DataSourceInterface::getRecords(array $configuration): iterable`
returns the records of a task. The processor passes the configuration of the
source if it implements `ConfigurableInterface`, otherwise an empty array.

Return a generator to read records lazily; the processor then holds only one
batch in memory (with `collectRecords: false`). Records read in advance with
`buildQueue()` are held in memory and lose their keys.

Optional interfaces:

| Interface | Purpose |
|---|---|
| `ResumableSourceInterface` | `getCursor()` returns the position after the last record yielded, `resumeFrom($cursor)` makes the next `getRecords()` start there. The processor stores the cursor in the checkpoint after each batch, see [State](State.md#checkpoints). |
| `CompletableSourceInterface` | `complete($result)` is called after `persistAll()` of the target, before the finishers, e.g. to mark read items as done. It is not called if processing fails. |

## Data targets

`Persistence\DataTargetInterface`:

* `persist(array|object $object, ?array $configuration = null)` is called for
  each record. The configuration is that of the target, or null if it is not
  configurable.
* `persistAll(mixed $result = null, ?array $configuration = null)` is called
  once after the last batch, if records were read.

Optional interfaces:

| Interface | Purpose |
|---|---|
| `BatchAwareInterface` | `flushBatch($result)` is called after each batch, e.g. to write buffered records and commit a transaction. Checkpoints are saved only for batch aware targets, because only they are known to have written the batch. |
| `IdentifyingTargetInterface` | `getTargetId($record)` returns the ID of a written record. Required for tasks with `idMap`, see [State](State.md#id-map). The processor asks after `flushBatch()` for batch aware targets, after `persist()` for others. |
| `Component\DisableableInterface` | `isDisabled($configuration, $record, $result)`: the target skips the record. |

## Files

The sources and targets in `File\` read and write CSV and XML files:

| Class | Purpose |
|---|---|
| `File\DataSourceCSV` | reads one record per row; resumable |
| `File\DataSourceXML` | reads one record per element, with `XMLReader` (`element`) or XPath (`expression`); resumable |
| `File\DataTargetCSV` | writes one row per record |
| `Persistence\NullDataSource`, `Persistence\NullDataTarget` | no records, nothing written |

Options: [Configuration/Components.md](../Configuration/Components.md).

### Resource loaders

Sources do not open files themselves. They pass the value of `file` or `url`
to a `Resource\ResourceLoaderInterface`, which returns a PSR-7 stream:

| Loader | Locations |
|---|---|
| `FileResourceLoader` (default) | paths, resolved by a `PathResolverInterface` |
| `HttpResourceLoader` | `http` and `https` URLs, with a PSR-18 client and a PSR-17 request factory (packages psr/http-client and psr/http-factory) |
| `DelegatingResourceLoader` | the first of its loaders that supports the location |

`Resource\PathResolver` resolves paths relative to a base directory, by
default the working directory. It rejects absolute paths, stream wrappers
(`scheme://`), `..` segments and symbolic links that lead out of the base
directory, so a configuration cannot read or write other files of the
system. `DataTargetCSV` uses a path resolver as well.

Loading URLs is not enabled by default. A configuration with `url` could
make the application request any address its HTTP client reaches, so an
application adds `HttpResourceLoader` only if it needs it, and restricts the
client to the hosts it expects.

The loader and the path resolver are constructor arguments. Without them,
`FileResourceLoader` and `PathResolver` with the working directory are used.
Applications pass their own, e.g. through the container and a
`ContainerInstantiator` (see [Components](Components.md#dependencies)):

```php
use CPSIT\ImportExportCore\File\DataSourceCSV;
use CPSIT\ImportExportCore\Resource\DelegatingResourceLoader;
use CPSIT\ImportExportCore\Resource\FileResourceLoader;
use CPSIT\ImportExportCore\Resource\HttpResourceLoader;
use CPSIT\ImportExportCore\Resource\PathResolver;

$loader = new DelegatingResourceLoader(
    new FileResourceLoader(new PathResolver('/var/import')),
    new HttpResourceLoader($httpClient, $requestFactory),
);
$source = new DataSourceCSV($loader);
```

The extension t3import_export resolves `EXT:` paths with its own path
resolver.

### Large files and resuming

Both sources return generators and read the file while the processor
processes the records, so only one batch is held in memory (`DataSourceXML`
with `expression` loads the whole document). Both implement
`ResumableSourceInterface`: `DataSourceCSV` continues at the stored position
in the file, `DataSourceXML` reads the file again and skips the records up
to the stored record number.

Records of files have no ID of their own. Use a field of the file as source
ID for the ID map, or `recordNumberField`, which adds the number of the
record. The record number only identifies a record as long as records are
not inserted or removed in the file.

## Queue

A queue stages records between two tasks: one task writes records into a
queue (`DataTargetQueue`), another one reads them in portions
(`DataSourceQueue`), e.g. once per scheduler run.

`Queue\QueueRepositoryInterface` stores the items:

| Method | Description |
|---|---|
| `enqueue($queue, $data, $requeue = false)` | adds a record unless an item with the same checksum exists; with `$requeue`, an existing item is set back to new |
| `claim($queue, $limit)` | returns up to `$limit` new items in the order they were added and marks them as processing |
| `complete($item)`, `fail($item)` | mark an item as finished or failed |
| `release($queue)` | sets items in processing back to new, e.g. after an interrupted run |
| `count($queue, $status)` | number of items with a `QueueItemStatus` |

Implementations: `InMemoryQueueRepository` (tests, single runs) and
`Dbal\Queue\DbalQueueRepository` (table `import_export_queue_item`). The
payload is an array, stored as JSON; the checksum is
`QueueItem::checksum($queue, $data)`.

`DataSourceQueue` claims the items it reads and marks them finished in
`complete()`. If a task fails, its items stay in processing until
`release()` is called.

## Database (Doctrine DBAL)

The classes in `Dbal\` require the package `doctrine/dbal` (^4.3), which is
only suggested by the core.

* `ConnectionProviderInterface` provides connections by name.
  `ConnectionRegistry` holds connections created elsewhere, e.g. by
  `DriverManager`. Sources, targets and pre-processors name the connection in
  their configuration (`connection`, default `default`), so a task can read
  from one database and write to another.
* `DataSourceDBAL` reads a table, with conditions (`where`, `filter`), and
  can read in pages and resume with `cursorField`. It implements
  `ResumableSourceInterface`.
* `DataTargetDBAL` inserts or updates rows (`key`) and reports their IDs
  (`idField`). It implements `BatchAwareInterface` (one transaction per
  batch) and `IdentifyingTargetInterface`.
* `RelationListPreProcessor` reads the related IDs of a record from a relation
  (MM) table.

Options: [Configuration/Components.md](../Configuration/Components.md).

```php
use CPSIT\ImportExportCore\Dbal\ConnectionRegistry;
use Doctrine\DBAL\DriverManager;

$connections = new ConnectionRegistry([
    'default' => DriverManager::getConnection($targetParams),
    'legacy' => DriverManager::getConnection($sourceParams),
]);
```

The components get the connection provider through their constructor, so
they are created with a `ContainerInstantiator`, see
[Components](Components.md#dependencies).

### Tables of the core

`DbalIdMap`, `DbalCheckpointStore` and `DbalQueueRepository` store their data
in own tables. They implement `Dbal\Schema\TableProviderInterface`, and
`Dbal\Schema\SchemaInstaller` creates the tables:

```php
use CPSIT\ImportExportCore\Dbal\Schema\SchemaInstaller;

$installer = new SchemaInstaller($connection);
$installer->getStatements($idMap, $checkpoints, $queue); // SQL, without executing it
$installer->install($idMap, $checkpoints, $queue);
```

Missing tables are created and missing columns are added. Nothing is changed
or dropped, so it can run repeatedly, e.g. in a setup command. Applications
with their own schema tools can use the table definitions of `getTables()`
instead.

| Class | Default table |
|---|---|
| `Dbal\State\DbalIdMap` | `import_export_id_map` |
| `Dbal\State\DbalCheckpointStore` | `import_export_checkpoint` |
| `Dbal\Queue\DbalQueueRepository` | `import_export_queue_item` |

The table name is the second constructor argument.

## Export streams

`Domain\Model\DataStreamInterface` holds the output of an export in a PSR-7
`StreamInterface` (`getStream()`, `setStream()`). `DataStream` starts with a
`Stream\TemporaryStream`, which keeps up to 2 MB in memory and moves larger
content to a temporary file. Targets write to the stream in chunks instead of
building one string.
