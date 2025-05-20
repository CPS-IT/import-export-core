<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Component\Converter;

use CPSIT\ImportExportCore\Component\ComponentInterface;
use CPSIT\ImportExportCore\Domain\Model\TaskResult;

/**
 * Interface ConverterInterface
 */
interface ConverterInterface extends ComponentInterface
{
    public function convert(array $record, array $configuration): mixed;

    /**
     * Tells if the component is disabled
     */
    public function isDisabled(array $configuration, array $record = [], ?TaskResult $result = null): bool;

    /**
     * @param array $configuration
     * @return bool
     */
    public function isConfigurationValid(array $configuration): bool;

    /**
     * Sets the configuration
     */
    public function setConfiguration(array $configuration): void;

    /**
     * Returns the configuration
     */
    public function getConfiguration(): array;
}