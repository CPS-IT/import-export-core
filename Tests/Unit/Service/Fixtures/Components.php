<?php

declare(strict_types=1);

/*
 * This file is part of the package cpsit/import-export-core.
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or (at
 * your option) any later version.
 *
 * For the full license information, please read the LICENSE file that was
 * distributed with this source code.
 */

namespace CPSIT\ImportExportCore\Tests\Unit\Service\Fixtures;

use CPSIT\ImportExportCore\Component\Converter\ConverterInterface;
use CPSIT\ImportExportCore\Component\DisableableInterface;
use CPSIT\ImportExportCore\Component\Finisher\FinisherInterface;
use CPSIT\ImportExportCore\Component\Initializer\InitializerInterface;
use CPSIT\ImportExportCore\Component\PostProcessor\PostProcessorInterface;
use CPSIT\ImportExportCore\Component\PreProcessor\PreProcessorInterface;
use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\LoggingTrait;
use CPSIT\ImportExportCore\Messaging\Message;
use CPSIT\ImportExportCore\Persistence\BatchAwareInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Persistence\IdentifyingTargetInterface;
use CPSIT\ImportExportCore\Persistence\ResumableSourceInterface;
use CPSIT\ImportExportCore\Pipeline;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Small component implementations for processor tests. Each one records how
 * it was called, and some write to a shared call log to check the order.
 */
abstract class AbstractTestComponent implements ConfigurableInterface
{
    use ConfigurableTrait;

    public bool $disabled = false;
    public int $calls = 0;

    /**
     * @param list<string> $log
     */
    public function __construct(public array &$log = [])
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return true;
    }

    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool
    {
        return $this->disabled;
    }
}

final class ArraySource implements DataSourceInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public int $reads = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastConfiguration = null;

    /**
     * @param iterable<mixed> $records
     */
    public function __construct(private readonly iterable $records)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        return true;
    }

    public function getRecords(array $configuration): iterable
    {
        ++$this->reads;
        $this->lastConfiguration = $configuration;

        return $this->records;
    }
}

final class InMemoryTarget implements DataTargetInterface, ConfigurableInterface, DisableableInterface
{
    use ConfigurableTrait;

    /**
     * @var array<mixed>
     */
    public array $persisted = [];

    /**
     * Records for which the target reports itself as disabled, by field "id"
     *
     * @var list<mixed>
     */
    public array $disabledForIds = [];

    /**
     * @var list<array{result: mixed, configuration: array<string, mixed>|null}>
     */
    public array $persistAllCalls = [];

    public function isConfigurationValid(array $configuration): bool
    {
        return true;
    }

    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool
    {
        return \in_array($record['id'] ?? null, $this->disabledForIds, true);
    }

    public function persist(array|object $object, ?array $configuration = null): bool
    {
        $this->persisted[] = $object;

        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): bool
    {
        $this->persistAllCalls[] = ['result' => $result, 'configuration' => $configuration];

        return true;
    }
}

/**
 * Appends one record with the configured id.
 */
final class AppendingInitializer extends AbstractTestComponent implements InitializerInterface
{
    public function process(array $configuration, array &$records): bool
    {
        ++$this->calls;
        $this->log[] = 'initializer';
        $records[] = ['id' => $configuration['id'] ?? 'appended'];

        return true;
    }
}

/**
 * Sets the configured field to the configured value.
 */
final class SetFieldPreProcessor extends AbstractTestComponent implements PreProcessorInterface
{
    public function process(array $configuration, array &$record): bool
    {
        ++$this->calls;
        $this->log[] = 'preProcessor:' . $record['id'];
        $record[(string)$configuration['field']] = $configuration['value'];

        return true;
    }
}

/**
 * Converts the record array into an object.
 */
final class ObjectConverter extends AbstractTestComponent implements ConverterInterface
{
    public function convert(array &$record, array $configuration): object
    {
        ++$this->calls;
        $this->log[] = 'converter:' . $record['id'];

        return (object)$record;
    }
}

/**
 * Marks the converted object and the original record.
 */
final class MarkingPostProcessor extends AbstractTestComponent implements PostProcessorInterface
{
    public function process(array $configuration, mixed &$convertedRecord, array &$record): bool
    {
        ++$this->calls;
        $this->log[] = 'postProcessor:' . $record['id'];
        if (\is_object($convertedRecord)) {
            $convertedRecord->postProcessed = true;
        }
        $record['postProcessed'] = true;

        return true;
    }
}

final class RecordingFinisher extends AbstractTestComponent implements FinisherInterface
{
    /**
     * @var array<mixed>|null
     */
    public ?array $records = null;

    public mixed $result = null;

    public function process(array $configuration, array &$records, array|object &$result): bool
    {
        ++$this->calls;
        $this->log[] = 'finisher';
        $this->records = $records;
        $this->result = $result;

        return true;
    }
}

/**
 * Tries to replace the result with an array.
 */
final class ReplacingFinisher extends AbstractTestComponent implements FinisherInterface
{
    public function process(array $configuration, array &$records, array|object &$result): bool
    {
        $result = ['replaced'];

        return true;
    }
}

/**
 * Reports one notice per processed record.
 */
final class LoggingPreProcessor extends AbstractTestComponent implements PreProcessorInterface, LoggingInterface
{
    use LoggingTrait;

    public function process(array $configuration, array &$record): bool
    {
        $this->logMessage('Processed', 'Record ' . $record['id'], Message::SEVERITY_NOTICE);

        return true;
    }
}

/**
 * Components implementing the pipeline contracts. They write to a shared
 * call log to check the order.
 */
abstract class AbstractPipelineComponent
{
    public int $calls = 0;

    /**
     * @param list<string> $log
     */
    public function __construct(public array &$log = [])
    {
    }
}

final class LogInitializer extends AbstractPipelineComponent implements Pipeline\InitializerInterface
{
    public ?Pipeline\TaskContext $context = null;

    public function initialize(Pipeline\TaskContext $context): void
    {
        ++$this->calls;
        $this->context = $context;
        $this->log[] = 'initializer';
    }
}

/**
 * Sets the configured field to the configured value.
 */
final class FieldPreProcessor extends AbstractPipelineComponent implements Pipeline\PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function isConfigurationValid(array $configuration): bool
    {
        return isset($configuration['field']);
    }

    public function process(array $record): array
    {
        ++$this->calls;
        $this->log[] = 'preProcessor:' . ($record['id'] ?? '');
        $record[(string)$this->configuration['field']] = $this->configuration['value'] ?? null;

        return $record;
    }
}

final class ToObjectConverter extends AbstractPipelineComponent implements Pipeline\ConverterInterface
{
    public function convert(array $record): object
    {
        ++$this->calls;
        $this->log[] = 'converter:' . $record['id'];

        return (object)$record;
    }
}

final class MarkPostProcessor extends AbstractPipelineComponent implements Pipeline\PostProcessorInterface
{
    public function process(mixed $convertedRecord, array $record): mixed
    {
        ++$this->calls;
        $this->log[] = 'postProcessor:' . $record['id'];
        if (\is_object($convertedRecord)) {
            $convertedRecord = clone $convertedRecord;
            $convertedRecord->postProcessed = true;
        }

        return $convertedRecord;
    }
}

final class ResultFinisher extends AbstractPipelineComponent implements Pipeline\FinisherInterface
{
    public ?ResultInterface $result = null;

    public ?Pipeline\TaskContext $context = null;

    public function finish(Pipeline\TaskContext $context): void
    {
        ++$this->calls;
        $this->log[] = 'finisher';
        $this->result = $context->result;
        $this->context = $context;
    }
}

/**
 * Target that records the number of persisted records at each batch flush.
 */
final class BatchAwareTarget implements DataTargetInterface, BatchAwareInterface
{
    public int $persisted = 0;

    /**
     * @var list<int>
     */
    public array $flushedAt = [];

    /**
     * @param list<string> $log
     */
    public function __construct(public array &$log = [])
    {
    }

    public function persist(array|object $object, ?array $configuration = null): bool
    {
        ++$this->persisted;

        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): bool
    {
        $this->log[] = 'persistAll';

        return true;
    }

    public function flushBatch(ResultInterface $result): void
    {
        $this->flushedAt[] = $this->persisted;
        $this->log[] = 'flush:' . $this->persisted;
    }
}

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    public array $events = [];

    /**
     * @param list<string> $log
     */
    public function __construct(public array &$log = [])
    {
    }

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        $this->log[] = 'event:' . (new \ReflectionClass($event))->getShortName();

        return $event;
    }
}

/**
 * Source of records with field "id" that can resume after an ID.
 */
final class CursorSource implements DataSourceInterface, ResumableSourceInterface
{
    public string $resumedFrom = '';

    private string $cursor = '';

    /**
     * @param list<array{id: int}> $records
     */
    public function __construct(private readonly array $records)
    {
    }

    public function getRecords(array $configuration): iterable
    {
        $started = $this->resumedFrom === '';
        foreach ($this->records as $record) {
            if (!$started) {
                $started = (string)$record['id'] === $this->resumedFrom;
                continue;
            }
            $this->cursor = (string)$record['id'];
            yield $record;
        }
    }

    public function getCursor(): string
    {
        return $this->cursor;
    }

    public function resumeFrom(string $cursor): void
    {
        $this->resumedFrom = $cursor;
    }
}

/**
 * Batch aware target that records the IDs of persisted records and fails when
 * persisting the record with the given ID.
 */
final class FailingBatchTarget implements DataTargetInterface, BatchAwareInterface
{
    /**
     * @var list<int>
     */
    public array $ids = [];

    public function __construct(public int $failAtId = 0)
    {
    }

    public function persist(array|object $object, ?array $configuration = null): bool
    {
        $id = \is_array($object) ? (int)$object['id'] : 0;
        if ($id === $this->failAtId) {
            throw new \RuntimeException('Persisting failed', 1794500100);
        }
        $this->ids[] = $id;

        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): bool
    {
        return true;
    }

    public function flushBatch(ResultInterface $result): void
    {
    }
}

/**
 * Target that assigns IDs starting at 100 and keeps the written records.
 */
final class IdentifyingTarget implements DataTargetInterface, IdentifyingTargetInterface
{
    /**
     * @var array<string, array<mixed>|object> target ID => record
     */
    public array $records = [];

    private int $lastId = 99;

    public function persist(array|object $object, ?array $configuration = null): bool
    {
        $this->records[(string)++$this->lastId] = $object;

        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): bool
    {
        return true;
    }

    public function getTargetId(array|object $record): string
    {
        $id = array_search($record, $this->records, true);
        if ($id === false) {
            throw new \OutOfBoundsException('Record was not persisted', 1794600101);
        }

        return (string)$id;
    }
}

/**
 * Batch aware target that assigns IDs (starting at 100) only when the batch is
 * flushed, like an ORM that writes on flush.
 */
final class FlushingIdentifyingTarget implements DataTargetInterface, BatchAwareInterface, IdentifyingTargetInterface
{
    /**
     * @var list<array<mixed>|object>
     */
    private array $pending = [];

    /**
     * @var array<string, array<mixed>|object> target ID => record
     */
    public array $records = [];

    private int $lastId = 99;

    public function persist(array|object $object, ?array $configuration = null): bool
    {
        $this->pending[] = $object;

        return true;
    }

    public function persistAll(mixed $result = null, ?array $configuration = null): bool
    {
        return true;
    }

    public function flushBatch(ResultInterface $result): void
    {
        foreach ($this->pending as $record) {
            $this->records[(string)++$this->lastId] = $record;
        }
        $this->pending = [];
    }

    public function getTargetId(array|object $record): string
    {
        $id = array_search($record, $this->records, true);
        if ($id === false) {
            throw new \OutOfBoundsException('Record was not flushed yet', 1794600102);
        }

        return (string)$id;
    }
}

/**
 * Accepts records whose field "id" is not in the list of rejected IDs.
 */
final class RejectingFilter extends AbstractPipelineComponent implements Pipeline\FilterInterface, DisableableInterface, LoggingInterface
{
    use LoggingTrait;

    /**
     * @var list<mixed>
     */
    public array $rejectedIds = [];

    public bool $disabled = false;

    public function accepts(array $record): bool
    {
        ++$this->calls;
        $this->log[] = 'filter:' . $record['id'];
        $this->logMessage('Filter', 'checked ' . $record['id']);

        return !\in_array($record['id'], $this->rejectedIds, true);
    }

    public function isDisabled(array $configuration, array $record = [], ?ResultInterface $result = null): bool
    {
        return $this->disabled;
    }
}
