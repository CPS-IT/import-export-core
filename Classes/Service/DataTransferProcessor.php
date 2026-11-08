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

        $this->processInitializers($records, $task, $result);

        if ($records !== []) {
            $targetConfiguration = $target instanceof ConfigurableInterface ? $target->getConfiguration() : null;

            foreach ($records as &$record) {
                $this->preProcessSingle($record, $task, $result);
                $convertedRecord = $this->convertSingle($record, $task, $result);
                $this->postProcessSingle($convertedRecord, $record, $task, $result);

                if (
                    $target instanceof DisableableInterface
                    && $target->isDisabled($targetConfiguration ?? [], $record, $result)
                ) {
                    continue;
                }

                $target->persist($convertedRecord, $targetConfiguration);
                $result->add($convertedRecord);
            }
            unset($record);

            $target->persistAll($result, $targetConfiguration);
        }

        $this->processFinishers($records, $task, $result);

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

    /**
     * @param array<mixed> $records
     */
    protected function processInitializers(array &$records, TransferTaskInterface $task, ResultInterface $result): void
    {
        foreach ($task->getInitializers() as $initializer) {
            $configuration = $initializer->getConfiguration();
            if (!$initializer->isDisabled($configuration, [], $result)) {
                $initializer->process($configuration, $records);
            }
            $this->gatherMessages($initializer, $result);
        }
    }

    protected function preProcessSingle(mixed &$record, TransferTaskInterface $task, ResultInterface $result): void
    {
        foreach ($task->getPreProcessors() as $preProcessor) {
            $configuration = $preProcessor->getConfiguration();
            if (!$preProcessor->isDisabled($configuration, $record, $result)) {
                $preProcessor->process($configuration, $record);
            }
            $this->gatherMessages($preProcessor, $result);
        }
    }

    protected function convertSingle(mixed $record, TransferTaskInterface $task, ResultInterface $result): mixed
    {
        $convertedRecord = $record;
        foreach ($task->getConverters() as $converter) {
            $configuration = $converter->getConfiguration();
            if (!$converter->isDisabled($configuration, $record, $result)) {
                $convertedRecord = $converter->convert($convertedRecord, $configuration);
            }
            $this->gatherMessages($converter, $result);
        }

        return $convertedRecord;
    }

    protected function postProcessSingle(
        mixed &$convertedRecord,
        mixed &$record,
        TransferTaskInterface $task,
        ResultInterface $result
    ): void {
        foreach ($task->getPostProcessors() as $postProcessor) {
            $configuration = $postProcessor->getConfiguration();
            if (!$postProcessor->isDisabled($configuration, $record, $result)) {
                $postProcessor->process($configuration, $convertedRecord, $record);
            }
            $this->gatherMessages($postProcessor, $result);
        }
    }

    /**
     * Finishers receive the result by reference. Changes to the result object
     * are kept; replacing it with another value has no effect.
     *
     * @param array<mixed> $records
     */
    protected function processFinishers(array &$records, TransferTaskInterface $task, ResultInterface $result): void
    {
        foreach ($task->getFinishers() as $finisher) {
            $configuration = $finisher->getConfiguration();
            if (!$finisher->isDisabled($configuration, [], $result)) {
                $finisherResult = $result;
                $finisher->process($configuration, $records, $finisherResult);
            }
            $this->gatherMessages($finisher, $result);
        }
    }

    protected function gatherMessages(object $component, ResultInterface $result): void
    {
        if ($component instanceof LoggingInterface) {
            $result->addMessages($component->getAndPurgeMessages());
        }
    }
}
