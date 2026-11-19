# Conditions and expressions

## Disabling components

Before the processor calls a component, it checks whether the component is
disabled for the current record (`DataTransferProcessor::isDisabled()`):

1. A component implementing `Component\DisableableInterface` decides itself:
   `isDisabled($configuration, $record, $result)`. The adapters of
   [legacy components](Legacy.md) do this.
2. Otherwise, if the component implements `ConfigurableInterface` and its
   configuration has the key `disable`, the value is passed to the
   `ConditionEvaluatorInterface` of the processor.
3. Otherwise the component is enabled.

Initializers and finishers are checked with an empty record. Data sources are
never checked. A data target is checked only if it implements
`DisableableInterface`; a disabled target skips the record, which is counted
as skipped.

The values of `disable` are described in
[Configuration](../Configuration/README.md#disabling-components).

## ConditionEvaluator

`Condition\ConditionEvaluator` implements
`isMet(mixed $condition, array $record = [], ?ResultInterface $result = null): bool`:

* `true`, `1` and `'1'` are met.
* An array with `if.result.hasMessage` (comma-separated message IDs, or one
  integer) is met if the result has a message with one of the IDs. Without a
  result it is not met.
* An array with `expression` is met if the expression is true. The
  expression gets the variables `record` and `result`. `result` is a
  read-only `Condition\ResultView` of the result (null without a result), so
  an expression cannot change the result. It provides `hasMessageWithId($id)`,
  `getMessageIds()`, `getInfo()` (scalar values only), `getRead()`,
  `getPersisted()` and `getSkipped()`.
* Everything else is not met.

If an array has both keys, the condition is met if one of them is met.

A different evaluator can be passed to the processor
(`conditionEvaluator`), e.g. to add variables or support other condition
types. The extension t3import_export uses the core evaluator.

## ExpressionEvaluator

`Expression\ExpressionEvaluator` evaluates expressions with the
[Symfony ExpressionLanguage](https://symfony.com/doc/current/reference/formats/expression_language.html).
It adds the functions `upper` (`mb_strtoupper`), `lower` (`mb_strtolower`),
`trim`, `date` and `strtotime`.

```php
use CPSIT\ImportExportCore\Expression\ExpressionEvaluator;

$evaluator = new ExpressionEvaluator();
$evaluator->evaluate("upper(record['title'])", ['record' => ['title' => 'news']]); // 'NEWS'
$evaluator->isTrue("record['type'] in ['news', 'event']", ['record' => $record]);
```

Errors raise an `InvalidConfigurationException`:

| Code | Cause |
|---|---|
| `ERROR_INVALID_EXPRESSION` (1794172347) | syntax error, unknown variable or function |
| `ERROR_EVALUATION_FAILED` (1795400001) | error while evaluating, e.g. a property of an array (`record.title` instead of `record['title']`), a method of null, or an argument of the wrong type |
| `ERROR_RANGE_TOO_LARGE` (1795400002) | a range (`a..b`) with more elements than the maximum (constructor argument `maximumRangeSize`, default 10000) |

A missing array key (`record['missing']`) evaluates to null without a PHP
warning; `record['missing'] ?? 'default'` gives a default value. `upper`,
`lower` and `trim` return `''` for null, `strtotime` returns false.

To add functions, pass a configured `ExpressionLanguage` to the constructor.
Components that evaluate expressions depend on
`ExpressionEvaluatorInterface`, so an application can replace the evaluator
in its container.

Expressions can only use the given variables and the registered functions.
They are still logic in the configuration, so configuration files should be
maintained by the same people as the code. `ValueRulesPreProcessor` covers
common cases without expressions.

## Pre-processor with expressions

`EvaluateExpressionsPreProcessor` sets fields from expressions, see
[Configuration/Components.md](../Configuration/Components.md#evaluateexpressionspreprocessor).
