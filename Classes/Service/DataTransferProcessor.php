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

namespace CPSIT\ImportExportCore\Service;

use CPSIT\ImportExportCore\Component\DisableableInterface;
use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\Domain\Model\Dto\DemandInterface;
use CPSIT\ImportExportCore\Domain\Model\ResultInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;
use CPSIT\ImportExportCore\Domain\Model\TransferTaskInterface;
use CPSIT\ImportExportCore\Event\BatchProcessed;
use CPSIT\ImportExportCore\Event\NullEventDispatcher;
use CPSIT\ImportExportCore\Event\TaskFinished;
use CPSIT\ImportExportCore\Event\TaskStarted;
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\Persistence\BatchAwareInterface;
use CPSIT\ImportExportCore\Persistence\CompletableSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Persistence\IdentifyingTargetInterface;
use CPSIT\ImportExportCore\Persistence\ResumableSourceInterface;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyAdapterInterface;
use CPSIT\ImportExportCore\Pipeline\TaskContext;
use CPSIT\ImportExportCore\State\Checkpoint;
use CPSIT\ImportExportCore\State\CheckpointStoreInterface;
use CPSIT\ImportExportCore\State\IdMapInterface;
use CPSIT\ImportExportCore\State\IdMapping;
use CPSIT\ImportExportCore\State\IdMapSettings;
use CPSIT\ImportExportCore\State\NullCheckpointStore;
use CPSIT\ImportExportCore\State\NullIdMap;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Processes transfer tasks: reads the records of the data source, runs them
 * through initializers, pre-processors, converters and post-processors,
 * persists them with the data target and runs the finishers.
 *
 * Records are read lazily and processed in batches of `$batchSize`. After each
 * batch, batch aware targets are flushed and `BatchProcessed` is dispatched.
 * `persistAll()` of the target is called once, after the last batch.
 *
 * Converted records are collected in the result unless `$collectRecords` is
 * false; the counters of the result are kept in any case. Memory usage is
 * bounded by the batch size only if records are not collected and the source
 * yields records lazily.
 *
 * Records can be read in advance for several tasks with buildQueue(), e.g. to
 * read all sources of a set before any task writes. Queued records are held in
 * memory.
 *
 * Initializers and finishers receive a `TaskContext` with the result, the ID
 * map and the checkpoint store. If the target is batch aware, a checkpoint is
 * saved after each batch and cleared when the task completed. A task with a
 * checkpoint continues after it: resumable sources continue from their
 * cursor, other sources are read from the beginning and the records already
 * processed are skipped. Without a checkpoint store, every run starts from the
 * beginning.
 *
 * Filters run first, on the record as read from the source; records they do
 * not accept are skipped. If the task has ID map settings, the target must
 * implement `IdentifyingTargetInterface`: after the records were written (after
 * `flushBatch()` for batch aware targets, after `persist()` for others), the
 * mapping from source ID to target ID is recorded with the checksum of the
 * source record.
 */
class DataTransferProcessor implements TransferProcessorInterface
{
    public const int DEFAULT_BATCH_SIZE = 100;

    /**
     * Records read in advance, by task key
     *
     * @var array<string, iterable<mixed>>
     */
    protected array $queue = [];

    public function __construct(
        protected readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
        protected readonly bool $collectRecords = true,
        protected readonly EventDispatcherInterface $eventDispatcher = new NullEventDispatcher(),
        protected readonly IdMapInterface $idMap = new NullIdMap(),
        protected readonly CheckpointStoreInterface $checkpointStore = new NullCheckpointStore(),
    ) {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('The batch size must be at least 1.', 1794400001);
        }
    }

    /**
     * @param array<string, iterable<mixed>> $queue
     */
    public function withQueue(array $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @return array<string, iterable<mixed>>
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * Reads the records of all tasks of the demand in advance.
     *
     * @throws InvalidConfigurationException if a task has no data source
     */
    public function buildQueue(DemandInterface $demand): void
    {
        foreach ($demand->getTasks() as $task) {
            $this->queue[$this->getTaskKey($task)] = $this->toArray($this->readRecords($task));
        }
    }

    public function process(TransferTaskInterface $task, ?ResultInterface $result = null): ResultInterface
    {
        $result ??= new TaskResult();
        $target = $this->getTarget($task);
        $idMapSettings = $task->getIdMapSettings();
        if ($idMapSettings->isEnabled() && !$target instanceof IdentifyingTargetInterface) {
            throw new InvalidConfigurationException(
                \sprintf(
                    'Task "%s" has ID map settings, but its target does not implement %s.',
                    $this->getTaskKey($task),
                    IdentifyingTargetInterface::class
                ),
                1794600031
            );
        }

        $key = $this->getTaskKey($task);
        $checkpoint = $this->checkpointStore->get($key);
        $source = $task->getSource();
        $resumableSource = null;
        $skip = $checkpoint->recordsProcessed;
        if (\array_key_exists($key, $this->queue)) {
            $records = $this->queue[$key];
            unset($this->queue[$key]);
        } else {
            if ($source instanceof ResumableSourceInterface) {
                $resumableSource = $source;
                if ($checkpoint->cursor !== '') {
                    $source->resumeFrom($checkpoint->cursor);
                    $skip = 0;
                }
            }
            $records = $this->readRecords($task);
        }

        $context = new TaskContext($key, $task, $result, $this->idMap, $this->checkpointStore, $checkpoint);
        $this->eventDispatcher->dispatch(new TaskStarted($task, $key));
        $this->processInitializers($task, $context);

        $recordCount = 0;
        $batchNumber = $checkpoint->batchNumber;
        $batch = [];
        foreach ($records as $record) {
            if ($skip > 0) {
                --$skip;
                continue;
            }
            $batch[] = $record;
            if (\count($batch) >= $this->batchSize) {
                $this->processBatch($batch, ++$batchNumber, $task, $key, $target, $result);
                $recordCount += \count($batch);
                $batch = [];
                $this->saveCheckpoint($key, $batchNumber, $checkpoint->recordsProcessed + $recordCount, $target, $resumableSource);
            }
        }
        if ($batch !== []) {
            $this->processBatch($batch, ++$batchNumber, $task, $key, $target, $result);
            $recordCount += \count($batch);
            $this->saveCheckpoint($key, $batchNumber, $checkpoint->recordsProcessed + $recordCount, $target, $resumableSource);
        }

        if ($recordCount > 0) {
            $target->persistAll($result, $this->getTargetConfiguration($target));
        }

        if ($source instanceof CompletableSourceInterface) {
            $source->complete($result);
        }

        $this->processFinishers($task, $context);
        $this->checkpointStore->clear($key);
        $this->eventDispatcher->dispatch(new TaskFinished($task, $key, $recordCount, $result));

        return $result;
    }

    /**
     * @param list<mixed> $batch
     */
    protected function processBatch(
        array $batch,
        int $batchNumber,
        TransferTaskInterface $task,
        string $key,
        DataTargetInterface $target,
        ResultInterface $result
    ): void {
        $statistics = $result->getStatistics();
        $targetConfiguration = $this->getTargetConfiguration($target);
        $idMapSettings = $task->getIdMapSettings();
        $needsArray = $task->getFilters() !== [] || $idMapSettings->isEnabled() || $task->getPreProcessors() !== []
            || $task->getConverters() !== [] || $task->getPostProcessors() !== [];

        $recordsToMap = $idMapSettings->isEnabled() && $target instanceof IdentifyingTargetInterface;
        $batchAware = $target instanceof BatchAwareInterface;
        /** @var list<array{array<mixed>, array<mixed>|object}> $written source record and persisted record */
        $written = [];

        foreach ($batch as $record) {
            $statistics->countRead();
            $sourceRecord = $record;
            if (\is_array($record)) {
                if (!$this->accepts($record, $task, $result)) {
                    $statistics->countSkipped();
                    continue;
                }
                $record = $this->preProcessSingle($record, $task, $result);
                $convertedRecord = $this->convertSingle($record, $task, $result);
                $convertedRecord = $this->postProcessSingle($convertedRecord, $record, $task, $result);
            } elseif ($needsArray) {
                throw new \UnexpectedValueException(
                    \sprintf('Task "%s": records must be arrays to be processed, got %s.', $key, get_debug_type($record)),
                    1794260001
                );
            } else {
                $convertedRecord = $record;
            }

            if (
                $target instanceof DisableableInterface
                && $target->isDisabled($targetConfiguration ?? [], \is_array($record) ? $record : [], $result)
            ) {
                $statistics->countSkipped();
                continue;
            }

            $target->persist($convertedRecord, $targetConfiguration);
            $statistics->countPersisted();
            if ($recordsToMap && \is_array($sourceRecord) && (\is_array($convertedRecord) || \is_object($convertedRecord))) {
                if ($batchAware) {
                    $written[] = [$sourceRecord, $convertedRecord];
                } else {
                    $this->recordMapping($sourceRecord, $convertedRecord, $target, $idMapSettings, $key);
                }
            }
            if ($this->collectRecords) {
                $result->add($convertedRecord);
            }
        }

        if ($target instanceof BatchAwareInterface) {
            $target->flushBatch($result);
            if ($target instanceof IdentifyingTargetInterface) {
                foreach ($written as [$sourceRecord, $persistedRecord]) {
                    $this->recordMapping($sourceRecord, $persistedRecord, $target, $idMapSettings, $key);
                }
            }
        }
        $statistics->countBatch();
        $this->eventDispatcher->dispatch(new BatchProcessed($task, $key, $batchNumber, \count($batch), $result));
    }

    /**
     * Saves a checkpoint after a batch. Only batch aware targets are known to
     * have written the batch at this point; for other targets, records may be
     * held until `persistAll()`.
     */
    protected function saveCheckpoint(
        string $key,
        int $batchNumber,
        int $recordsProcessed,
        DataTargetInterface $target,
        ?ResumableSourceInterface $source
    ): void {
        if (!$target instanceof BatchAwareInterface) {
            return;
        }
        $cursor = $source instanceof ResumableSourceInterface ? $source->getCursor() : '';
        $this->checkpointStore->save(new Checkpoint($key, $batchNumber, $recordsProcessed, $cursor));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getTargetConfiguration(DataTargetInterface $target): ?array
    {
        return $target instanceof ConfigurableInterface ? $target->getConfiguration() : null;
    }

    /**
     * @return iterable<mixed>
     */
    protected function readRecords(TransferTaskInterface $task): iterable
    {
        $source = $task->getSource();
        if (!$source instanceof DataSourceInterface) {
            throw new InvalidConfigurationException(
                \sprintf('Task "%s" has no data source.', $this->getTaskKey($task)),
                1794160001
            );
        }

        $configuration = $source instanceof ConfigurableInterface ? $source->getConfiguration() : [];

        return $source->getRecords($configuration);
    }

    protected function getTarget(TransferTaskInterface $task): DataTargetInterface
    {
        $target = $task->getTarget();
        if (!$target instanceof DataTargetInterface) {
            throw new InvalidConfigurationException(
                \sprintf('Task "%s" has no data target.', $this->getTaskKey($task)),
                1794160002
            );
        }

        return $target;
    }

    /**
     * Tasks are identified by their identifier. Tasks without identifier are
     * identified by object.
     */
    protected function getTaskKey(TransferTaskInterface $task): string
    {
        if ($task instanceof IdentifiableInterface && $task->getIdentifier() !== null) {
            return $task->getIdentifier();
        }

        return 'object:' . spl_object_id($task);
    }

    /**
     * @param iterable<mixed> $records
     * @return array<mixed>
     */
    protected function toArray(iterable $records): array
    {
        return \is_array($records) ? $records : iterator_to_array($records, false);
    }

    protected function processInitializers(TransferTaskInterface $task, TaskContext $context): void
    {
        foreach ($task->getInitializers() as $initializer) {
            if (!$this->isDisabled($initializer, [], $context->result)) {
                $initializer->initialize($context);
            }
            $this->gatherMessages($initializer, $context->result);
        }
    }

    /**
     * Whether all filters accept the record.
     *
     * @param array<mixed> $record
     */
    protected function accepts(array $record, TransferTaskInterface $task, ResultInterface $result): bool
    {
        foreach ($task->getFilters() as $filter) {
            if ($this->isDisabled($filter, $record, $result)) {
                continue;
            }
            $accepted = $filter->accepts($record);
            $this->gatherMessages($filter, $result);
            if (!$accepted) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $sourceRecord
     * @param array<mixed>|object $persistedRecord
     */
    protected function recordMapping(
        array $sourceRecord,
        array|object $persistedRecord,
        IdentifyingTargetInterface $target,
        IdMapSettings $settings,
        string $key
    ): void {
        if (!$settings->isEnabled()) {
            return;
        }
        $sourceId = $sourceRecord[$settings->sourceIdField] ?? null;
        if (!\is_scalar($sourceId) || (string)$sourceId === '') {
            throw new \UnexpectedValueException(
                \sprintf('Task "%s": the source record has no ID in field "%s".', $key, $settings->sourceIdField),
                1794600032
            );
        }

        $this->idMap->set(new IdMapping(
            $settings->namespace,
            (string)$sourceId,
            $target->getTargetId($persistedRecord),
            IdMapping::checksum($sourceRecord)
        ));
    }

    /**
     * @param array<mixed> $record
     * @return array<mixed>
     */
    protected function preProcessSingle(array $record, TransferTaskInterface $task, ResultInterface $result): array
    {
        foreach ($task->getPreProcessors() as $preProcessor) {
            if (!$this->isDisabled($preProcessor, $record, $result)) {
                $record = $preProcessor->process($record);
            }
            $this->gatherMessages($preProcessor, $result);
        }

        return $record;
    }

    /**
     * Each converter receives the output of the previous one.
     *
     * @param array<mixed> $record
     */
    protected function convertSingle(array $record, TransferTaskInterface $task, ResultInterface $result): mixed
    {
        $convertedRecord = $record;
        foreach ($task->getConverters() as $converter) {
            if (!$this->isDisabled($converter, $record, $result)) {
                if (!\is_array($convertedRecord)) {
                    throw new \UnexpectedValueException(
                        \sprintf('Converters can only be chained while their output is an array, got %s.', get_debug_type($convertedRecord)),
                        1794260002
                    );
                }
                $convertedRecord = $converter->convert($convertedRecord);
            }
            $this->gatherMessages($converter, $result);
        }

        return $convertedRecord;
    }

    /**
     * @param array<mixed> $record
     */
    protected function postProcessSingle(
        mixed $convertedRecord,
        array $record,
        TransferTaskInterface $task,
        ResultInterface $result
    ): mixed {
        foreach ($task->getPostProcessors() as $postProcessor) {
            if (!$this->isDisabled($postProcessor, $record, $result)) {
                $convertedRecord = $postProcessor->process($convertedRecord, $record);
            }
            $this->gatherMessages($postProcessor, $result);
        }

        return $convertedRecord;
    }

    protected function processFinishers(TransferTaskInterface $task, TaskContext $context): void
    {
        foreach ($task->getFinishers() as $finisher) {
            if (!$this->isDisabled($finisher, [], $context->result)) {
                $finisher->finish($context);
            }
            $this->gatherMessages($finisher, $context->result);
        }
    }

    /**
     * Components can be disabled only if they implement
     * `DisableableInterface`. Adapters of deprecated components do.
     *
     * @param array<mixed> $record
     */
    protected function isDisabled(object $component, array $record, ResultInterface $result): bool
    {
        if (!$component instanceof DisableableInterface) {
            return false;
        }
        $configuration = $component instanceof ConfigurableInterface ? $component->getConfiguration() : [];

        return $component->isDisabled($configuration, $record, $result);
    }

    protected function gatherMessages(object $component, ResultInterface $result): void
    {
        if ($component instanceof LegacyAdapterInterface) {
            $component = $component->getComponent();
        }
        if ($component instanceof LoggingInterface) {
            $result->addMessages($component->getAndPurgeMessages());
        }
    }
}
