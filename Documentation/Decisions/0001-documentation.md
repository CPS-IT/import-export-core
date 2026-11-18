# 1. Documentation

Status: accepted (2026-11-18), issue
[#76](https://github.com/CPS-IT/import-export-core/issues/76)

## Context

Besides the README, CONTRIBUTING, UPGRADE, the CHANGELOG and
`Documentation/Architecture.md`, the core has no documentation. The options of
components are described in docblocks only, and not in the same format.

Components moved from the extension t3import_export into the core. People who
configure tasks (integrators) therefore need the configuration reference of
the core, also when they use the core through the extension.

Three audiences:

* contributors: architecture, development setup, tests, release process
* developers: interfaces, pipeline contracts, registry and factories,
  conditions and expressions, events, state, legacy adapters, deprecations
  and migration
* integrators: the configuration schema of tasks and sets and the options of
  every component

Options considered (details in #76): hand-written Markdown, phpDocumentor 3,
Doctum, the TYPO3 render-guides, MkDocs or VitePress, and a configuration
reference generated from the code.

## Decision

1. Guides are hand-written Markdown in `Documentation/`, read on GitHub. No
   site generator.
2. The configuration reference of the components is generated from the
   docblocks of the component classes into Markdown and committed. CI fails if
   the committed reference is not current, or if a component has no
   configuration section. Typed configuration definitions (e.g. JSON Schema)
   may replace the docblocks later; the generated pages keep their place.
3. The API reference is generated with phpDocumentor 3 in CI and published
   with GitHub Pages. phpDocumentor is not a Composer dependency of the
   package.
4. The documentation of the extension follows the TYPO3 render-guides
   (docs.typo3.org). It links to the configuration reference of the core
   instead of copying it. If integrators need the reference inside the
   extension documentation, the generator gets a second output format (reST).

## Structure

```
Documentation/
  README.md                  index, by audience
  Architecture.md            processing model, namespaces (contributors, developers)
  Decisions/                 decision records
  Configuration/
    README.md                configuration schema of tasks and sets
    Components.md            generated: options of every component
  Developer/
    Components.md            writing components, registry, factories, DI
    Conditions.md            disable, conditions, expressions
    Persistence.md           sources, targets, queue, DBAL
    State.md                 ID map, checkpoints, incremental mode
    Processing.md            processor, order of processing, result, events
    Legacy.md                legacy contracts, adapters, deprecations
```

CONTRIBUTING, UPGRADE and CHANGELOG stay in the root directory.

## Docblock convention

The class docblock of a component starts with a summary. The options follow
under the line `Configuration:`, as a Markdown list:

```php
/**
 * Writes records into a queue.
 *
 * Configuration:
 *
 * - `identifier` (string, required): the queue
 * - `allowUpdate` (bool, default false): records already in the queue are
 *   set back to new
 */
```

* one item per option: name, type and `required` or the default in
  parentheses, then the description
* continuation lines and nested items are indented by two spaces
* a component without options has the line `Configuration: none.`
* the section ends at the first tag (`@...`) or the end of the docblock

## Consequences

* Existing docblocks are converted to the convention.
* New components need a configuration section, or CI fails.
* GitHub Pages must be enabled for the repository (source: GitHub Actions).
* The guides are written one by one; the index lists missing pages.
