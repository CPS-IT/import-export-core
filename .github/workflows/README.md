# GitHub Actions workflows

This file lives in `.github/workflows/`, not in `.github/`: GitHub shows a
`README.md` in `.github/` on the repository start page instead of the root
`README.md`.

## Workflows

| Workflow | File | Jobs |
|----------|------|------|
| Tests | `tests.yml` | Unit tests with PHP 8.3 and 8.4, coverage report (uploaded to Codecov) |
| Code Quality | `code-quality.yml` | PHP-CS-Fixer, PHPStan, EditorConfig |
| Composer Validation | `composer-validate.yml` | `composer validate`, normalized `composer.json` |

## Triggers

* pushes to `main`, `feature/**` and `support/**`
* all pull requests

## Required checks

Pull requests into `main` and `support/*` can only be merged when these jobs
pass: Validate composer.json, PHP-CS-Fixer, EditorConfig, PHPStan Static
Analysis, Unit Tests with PHP 8.3, Unit Tests with PHP 8.4. The coverage
report is not required, because it depends on the external Codecov service.

The same checks run locally with `composer lint`, `composer sca` and
`composer test:unit`.
