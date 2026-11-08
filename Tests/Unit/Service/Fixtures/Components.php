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
use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;

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
