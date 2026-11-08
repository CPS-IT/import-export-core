# GitHub Actions for import-export-core

This directory contains GitHub Actions workflows for automated testing and quality assurance.

## Available Workflows

### 1. Tests (`tests.yml`)
Runs unit tests with different PHP versions.
- Tests PHP 8.3 and 8.4
- Generates code coverage reports
- Uploads coverage to Codecov

### 2. Code Quality (`code-quality.yml`)
Runs code quality checks:
- PHP-CS-Fixer: Ensures code style compliance
- PHPStan: Performs static code analysis

### 3. Composer Validation (`composer-validate.yml`)
Validates the composer.json file:
- Checks syntax and dependencies
- Ensures composer.json is normalized

## Status Badges

You can add these status badges to your README.md:

```markdown
[![Tests](https://github.com/CPS-IT/import-export-core/actions/workflows/tests.yml/badge.svg)](https://github.com/CPS-IT/import-export-core/actions/workflows/tests.yml)
[![Code Quality](https://github.com/CPS-IT/import-export-core/actions/workflows/code-quality.yml/badge.svg)](https://github.com/CPS-IT/import-export-core/actions/workflows/code-quality.yml)
[![Composer Validation](https://github.com/CPS-IT/import-export-core/actions/workflows/composer-validate.yml/badge.svg)](https://github.com/CPS-IT/import-export-core/actions/workflows/composer-validate.yml)
[![Codecov](https://codecov.io/gh/CPS-IT/import-export-core/branch/main/graph/badge.svg)](https://codecov.io/gh/CPS-IT/import-export-core)
```

## Workflow Triggers

All workflows are triggered on:
- Push to `master`, `main`, `develop`, and `feature/**` branches
- Pull requests to `master`, `main`, and `develop` branches

## Coverage Integration

The test workflow automatically uploads coverage reports to Codecov for tracking code coverage over time.