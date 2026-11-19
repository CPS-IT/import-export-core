# Configuration of tasks and sets

An import or export is configured as tasks and sets. A task reads records
from one data source, processes them and writes them to one data target. A
set runs several tasks one after the other.

The options of the components are listed in
[Components.md](Components.md).

## File structure

```yaml
import:
  tasks:
    news:                     # task identifier
      label: News
      source: { ... }
      target: { ... }
  sets:
    all:                      # set identifier
      tasks: news, events
export:
  tasks: { ... }
  sets: { ... }
```

`YamlConfigurationLoader` keeps the `tasks` and `sets` of `import` and
`export`; other keys of the file are ignored.

Several files can be combined with `ConfigurationHandler::addConfiguration()`,
or all files of a directory, in alphabetical order, with
`YamlConfigurationProvider::loadFromDirectory()`. Later files change earlier
ones: maps are merged recursively, scalar values
and lists replace the earlier value. Component lists keyed by position
(`10`, `20`) are maps, so a later file can replace single components or add
new ones.
Lists without keys (`- class: ...`) are replaced as a whole.

## Task

| Key | Type | Description |
|---|---|---|
| `source` | component, required | data source |
| `target` | component, required | data target |
| `label`, `description` | string | shown by applications, e.g. in a backend module |
| `class` | string | class of the converted records, for applications that need it |
| `idMap` | map | `namespace` and `sourceIdField`, both strings, and optionally `targetIdField`; see [ID map](../Developer/State.md#id-map) |
| `initializers` | map of components | run once before the records are read |
| `filters` | map of components | decide whether a record is processed |
| `preProcessors` | map of components | change the record |
| `converters` | map of components | convert the record, e.g. into an object |
| `postProcessors` | map of components | change the converted record |
| `finishers` | map of components | run once after all records were written |

The components of a list run in the order in which they appear in the
configuration; the keys are not sorted. A component that a later file adds
under a new key runs after the existing ones, whatever its key.

## Component

| Key | Type | Description |
|---|---|---|
| `class` | string | fully qualified class name or a short name registered for the role. Without `class`, the default class of the role is used, if the application registered one. |
| `config` | map | the options of the component, see [Components.md](Components.md). A missing `config` is passed as an empty map. |
| `identifier` | string | for components implementing `IdentifiableInterface` |

The configuration is validated when the task is created. An invalid
configuration raises an `InvalidConfigurationException` that names the task.

Example:

```yaml
import:
  tasks:
    pages:
      label: Pages
      idMap:
        namespace: pages
        sourceIdField: uid
      source:
        class: CPSIT\ImportExportCore\Dbal\DataSourceDBAL
        config:
          connection: legacy
          table: pages
          cursorField: uid
      preProcessors:
        10:
          class: CPSIT\ImportExportCore\State\Component\TargetIdPreProcessor
          config:
            namespace: pages
            sourceIdField: uid
            targetIdField: uid
        20:
          class: CPSIT\ImportExportCore\Processor\SetValuesPreProcessor
          config:
            values:
              doktype: 1
            disable:
              expression: "record['hidden'] == 1"
      target:
        class: CPSIT\ImportExportCore\Dbal\DataTargetDBAL
        config:
          table: pages
          key: [uid]
          idField: uid
```

## Disabling components

Initializers, filters, pre-processors, converters, post-processors and
finishers accept the option `disable` in `config`. Data sources are never
disabled; data targets only if their class implements
`DisableableInterface`, which then decides itself.

| Value | Effect |
|---|---|
| `true`, `1`, `'1'` | always disabled |
| `{expression: "..."}` | disabled if the expression is true. Variables: `record` (the current record; empty for initializers and finishers) and `result` |
| `{if: {result: {hasMessage: '1234,5678'}}}` | disabled if the result contains a message with one of the IDs |

If both `if.result.hasMessage` and `expression` are given, the component is
disabled if one of them applies. Expressions use the
[Symfony ExpressionLanguage](https://symfony.com/doc/current/reference/formats/expression_language.html)
with the additional functions `upper`, `lower`, `trim`, `date` and
`strtotime`. An invalid expression raises an `InvalidConfigurationException`
when it is evaluated. Details: [Conditions and expressions](../Developer/Conditions.md).

A disabled component is skipped for this record only; the record continues
with the next component.

## Set

| Key | Type | Description |
|---|---|---|
| `tasks` | list or comma-separated string, required | task identifiers of the same section (`import` or `export`), in order |
| `label`, `description` | string | shown by applications |

A set that names an undefined task raises an `InvalidConfigurationException`.
