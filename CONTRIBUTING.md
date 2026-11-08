# Contributing

Thank you for contributing to the Import & Export core library. This document
describes how the repository is organised and how changes get from an idea into
a release.

The library is used by the TYPO3 extension
[Import & Export](https://github.com/dwenzel/t3import_export)
(`t3import_export`). Both repositories follow the same branching model.

## Development environment

The library has no TYPO3 dependency. Tests and linters run with PHP 8.3 or
newer and Composer:

```bash
composer install
composer test:unit
composer lint
composer sca
```

To work on the library together with the extension, use the DDEV based
environment [t3import-export-bundle](https://github.com/dwenzel/t3import-export-bundle).
It checks out this repository into `packages/import-export-core`; the TYPO3 13
instance uses that working copy instead of the released package.

## Branching model

The repository follows a branching model adapted from GitLab Flow.

### main

* `main` contains the current development.
* `main` is protected. Changes are integrated only through pull requests,
  after the automated checks have passed.
* There are no `develop` or `release/*` branches.

### Support branches

If an older major version needs fixes after a new major version has been
released, a support branch is created from the last tag of that version:

```
support/{major}.x        e.g. support/1.x
```

* Fixes for an older major version are merged into its support branch through
  pull requests.
* Support branches are protected like `main`.

### Feature branches

All work happens in feature branches. Every change starts with a GitHub issue;
name the branch after it:

```
feature/{issue-number}-{title-in-kebab-case}
feature/{title-in-kebab-case}        # only if there is no issue
```

Examples: `feature/4-editorconfig`, `feature/fix-yaml-parser`

* Branch from `main`, or from `support/{major}.x` if the change only concerns
  an older major version.
* Branch names consist of lowercase alphanumeric characters and `-`. Other
  characters are not allowed, apart from the `/` after the prefix.
* The prefixes `environment/`, `hotfix/` and `renovate/` and the names `live`,
  `production`, `pre-production`, `staging`, `integration`, `hardening` and
  `acceptance` are reserved.

Branches that are no longer maintained but should not be lost are kept as tags
under `archive/` (for example `archive/develop`).

## Workflow

1. Create or pick a GitHub issue.
2. Create a feature branch from the target branch.
3. Make the change, including tests. Add an entry to `CHANGELOG.md`. For a
   breaking change or a deprecation, also add an entry to `UPGRADE.md`
   describing who is affected and how to migrate.
4. Run tests and linters locally.
5. Open a pull request into the target branch and reference the issue
   (`Closes #12`).
6. The pull request is merged after the checks have passed and it has been
   reviewed.
7. Delete the feature branch after merging.

## Commit messages

Commit messages are short and start with one of these tags:

`[FEATURE]` `[TASK]` `[BUGFIX]` `[DOC]` `[CI]` `[SECURITY]` `[RELEASE]` `[WIP]` `[DRAFT]`

Example: `[BUGFIX] Keep numeric keys when parsing YAML configuration`

`[WIP]` and `[DRAFT]` commits are not merged into `main` or a support branch.

## Releases

* Only tags are released. Tags follow [Semantic Versioning](https://semver.org/)
  without prefix (e.g. `0.1.0`).
* While the version is `0.x`, a new minor version may contain breaking changes;
  patch versions do not.
* The package is published on Packagist; new tags are picked up automatically.
* Release steps:
  1. Update `CHANGELOG.md` and, if needed, the branch alias in
     `composer.json` in a feature branch and merge it through a pull request
     (commit tag `[RELEASE]`).
  2. Tag the merge commit on `main` or on the support branch.
  3. Update the dependent extension (`cpsit/import-export-core` constraint in
     `t3import_export`) if it needs the new version.
