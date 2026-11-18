# Legacy components and deprecations

## Deprecated component interfaces

Before 0.2.0, components implemented the interfaces in
`CPSIT\ImportExportCore\Component\`. They changed their arguments by
reference and received their configuration with each call. These interfaces
are deprecated since 0.2.0:

| Deprecated (`Component\`) | Replacement (`Pipeline\`) |
|---|---|
| `Initializer\InitializerInterface::process(array $configuration, array &$records): bool` | `InitializerInterface::initialize(TaskContext $context): void` |
| `PreProcessor\PreProcessorInterface::process(array $configuration, array &$record): bool` | `PreProcessorInterface::process(array $record): array` |
| `Converter\ConverterInterface::convert(array &$record, array $configuration): mixed` | `ConverterInterface::convert(array $record): mixed` |
| `PostProcessor\PostProcessorInterface::process(array $configuration, mixed &$convertedRecord, array &$record): bool` | `PostProcessorInterface::process(mixed $convertedRecord, array $record): mixed` |
| `Finisher\FinisherInterface::process(array $configuration, array &$records, array\|object &$result): bool` | `FinisherInterface::finish(TaskContext $context): void` |

`Component\ComponentInterface`, `Component\DisableableInterface` and
`Component\NullComponent` are not deprecated.

## Adapters

Components implementing a deprecated interface keep working.
`ComponentFactory` and `TransferTask` wrap them in an adapter
(`Pipeline\Legacy\Legacy*Adapter`), which implements the pipeline interface:

* The adapter passes the configuration of the component with each call, as
  before.
* It implements `DisableableInterface` and delegates `isDisabled()` to the
  component. Such components therefore decide themselves, e.g. with the
  option `disabled`; the processor does not evaluate `disable` for them.
* Messages of the wrapped component are collected as before.
* `TransferTask::get*()` return the adapters.
  `LegacyAdapterInterface::getComponent()` returns the wrapped component.

Differences to the behaviour before 0.2.0:

* Initializers receive an empty list of records; records they add are not
  processed. They do not get the `TaskContext`, so they cannot tell whether a
  run resumes. Migrate initializers that reset the target before using a
  checkpoint store, see [State](State.md#checkpoints).
* Finishers receive an empty list of records and the result of the task.
* Changes of post-processors to `$record` are discarded; changes to
  `$convertedRecord` are kept.

`ComponentRegistry::register()` and `setDefault()` accept classes that
implement the deprecated counterpart of the role's interface.

The adapters and `LegacyComponents` are internal. Applications should not
create them; they are removed together with the deprecated interfaces.

## Migrating a component

1. Implement the interface in `Pipeline\` instead of the one in
   `Component\`.
2. Read the configuration from the component (`ConfigurableTrait`) instead of
   the method argument.
3. Return the changed record instead of changing the argument.
4. Remove `isDisabled()`, unless the component has to decide itself; then
   implement `Component\DisableableInterface`. Configurations use `disable`
   instead of `disabled`, see [Conditions](Conditions.md).
5. Document the options in the class docblock, see
   [Components](Components.md#documenting-options).

```php
// before
final class SetTitle implements \CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface
{
    public function process(array $configuration, array &$record): bool
    {
        $record['title'] = $configuration['value'];
        return true;
    }
    // isDisabled(), isConfigurationValid(), setConfiguration(), getConfiguration()
}

// after
final class SetTitle implements \CPSIT\ImportExportCore\Pipeline\PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function process(array $record): array
    {
        $record['title'] = $this->configuration['value'];
        return $record;
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return isset($configuration['value']);
    }
}
```

## Other deprecations

| Deprecated | Since | Replacement |
|---|---|---|
| `DataStreamInterface::setStreamBuffer()`, `getStreamBuffer()` | 0.2.0 | `setStream()`, `getStream()` (PSR-7 stream) |

Every breaking change and deprecation is described with its migration in
[UPGRADE.md](../../UPGRADE.md). The extension t3import_export lists its own
deprecations, including the components that moved into the core, in its
`Documentation/DEPRECATIONS.md`.
