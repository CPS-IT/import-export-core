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
use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;
use CPSIT\ImportExportCore\IdentifiableInterface;
use CPSIT\ImportExportCore\LoggingInterface;
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Pipeline\Legacy\LegacyAdapterInterface;

/**
 * Processes transfer tasks: reads the records of the data source, runs them
 * through initializers, pre-processors, converters and post-processors,
 * persists them with the data target and runs the finishers.
 *
 * All records of a task are held in memory. Records can be read in advance
 * for several tasks with buildQueue(), e.g. to read all sources of a set
 * before any task writes.
 */
class DataTransferProcessor implements TransferProcessorInterface
{
    /**
     * Records read in advance, by task key
     *
     * @var array<string, iterable<mixed>>
     */
    protected array $queue = [];

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
            $this->queue[$this->getTaskKey($task)] = $this->readRecords($task);
        }
    }

    public function process(TransferTaskInterface $task, ?ResultInterface $result = null): ResultInterface
    {
        $result ??= new TaskResult();
        $target = $this->getTarget($task);

        $key = $this->getTaskKey($task);
        $records = $this->toArray($this->queue[$key] ?? $this->readRecords($task));
        unset($this->queue[$key]);

        $this->processInitializers($task, $result);

        if ($records !== []) {
            $targetConfiguration = $target instanceof ConfigurableInterface ? $target->getConfiguration() : null;
            $needsArray = $task->getPreProcessors() !== [] || $task->getConverters() !== [] || $task->getPostProcessors() !== [];

            foreach ($records as $record) {
                if (\is_array($record)) {
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
                    continue;
                }

                $target->persist($convertedRecord, $targetConfiguration);
                $result->add($convertedRecord);
            }

            $target->persistAll($result, $targetConfiguration);
        }

        $this->processFinishers($task, $result);

        return $result;
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

    protected function processInitializers(TransferTaskInterface $task, ResultInterface $result): void
    {
        foreach ($task->getInitializers() as $initializer) {
            if (!$this->isDisabled($initializer, [], $result)) {
                $initializer->initialize();
            }
            $this->gatherMessages($initializer, $result);
        }
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

    protected function processFinishers(TransferTaskInterface $task, ResultInterface $result): void
    {
        foreach ($task->getFinishers() as $finisher) {
            if (!$this->isDisabled($finisher, [], $result)) {
                $finisher->finish($result);
            }
            $this->gatherMessages($finisher, $result);
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
