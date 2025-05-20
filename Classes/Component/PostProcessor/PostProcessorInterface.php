<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Component\PostProcessor;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;

/**
 * Interface PostProcessorInterface
 */
interface PostProcessorInterface extends ComponentInterface
{
    /**
     * @param array $configuration
     * @param mixed $convertedRecord
     * @param array $record
     * @return bool
     */
    public function process(array $configuration, mixed &$convertedRecord, array &$record): bool;

    public function isConfigurationValid(array $configuration): bool;

    /**
     * Tells if the component is disabled
     */
    public function isDisabled(array $configuration, array $record = [], ?TaskResult $result = null): bool;

    /**
     * Sets the configuration
     */
    public function setConfiguration(array $configuration): void;

    /**
     * Returns the configuration
     */
    public function getConfiguration(): array;
}
