# Documentation

## Integrators

People who configure tasks and sets.

* [Configuration of components](Configuration/Components.md): options of
  every component (generated)
* Configuration schema of tasks and sets: not written yet

## Developers

People who write components or use the library in an application.

* [README](../README.md): installation and usage
* [Architecture](Architecture.md): processing model and namespaces
* [UPGRADE](../UPGRADE.md): migration for breaking changes
* Not written yet: writing components, registry and factories; conditions
  and expressions; sources, targets and queue; ID map and checkpoints;
  events; legacy adapters and deprecations
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
