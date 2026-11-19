# Documentation

## Integrators

People who configure tasks and sets.

* [Configuration of tasks and sets](Configuration/README.md): file
  structure, tasks, sets, components, `disable`
* [Configuration of components](Configuration/Components.md): options of
  every component (generated)

## Developers

People who write components or use the library in an application.

* [README](../README.md): installation and usage
* [Architecture](Architecture.md): processing model and namespaces
* [UPGRADE](../UPGRADE.md): migration for breaking changes
* [Processing tasks](Developer/Processing.md): factories, processor, order
  of processing, result, events
* [Writing components](Developer/Components.md): interfaces, configuration,
  documenting options, dependencies, short names, messages, tests
* [Conditions and expressions](Developer/Conditions.md)
* [Sources, targets and queue](Developer/Persistence.md), including files
  and DBAL
* [ID map, checkpoints and incremental mode](Developer/State.md)
* [Legacy components and deprecations](Developer/Legacy.md)
* [API reference](https://cps-it.github.io/import-export-core/), generated with
  phpDocumentor from `main` (`phpdoc.dist.xml`)

## Contributors

* [CONTRIBUTING](../CONTRIBUTING.md): development setup, branching model,
  workflow and releases
* [Architecture](Architecture.md)
* [Decisions](Decisions/): decision records, e.g.
  [1. Documentation](Decisions/0001-documentation.md)

The configuration reference is generated from the class docblocks of the
components (convention in [decision 1](Decisions/0001-documentation.md)):

```bash
composer docs:reference   # write Documentation/Configuration/Components.md
composer lint:docs        # check that it is current; part of composer lint
```
