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

namespace CPSIT\ImportExportCore\State;

use CPSIT\ImportExportCore\Exception\InvalidConfigurationException;

/**
 * Tells the processor to record the target ID of each written record in the
 * ID map: under which namespace, and which field of the source record holds
 * the source ID. Without a namespace, nothing is recorded.
 *
 * `targetIdField` names the field in which `TargetIdPreProcessor` sets the
 * target ID, if it differs from `sourceIdField`. It is left out of the
 * checksum, like the source ID field.
 */
final readonly class IdMapSettings
{
    public function __construct(
        public string $namespace = '',
        public string $sourceIdField = '',
        public string $targetIdField = '',
    ) {
        if (($namespace === '') !== ($sourceIdField === '')) {
            throw new \InvalidArgumentException('Namespace and source ID field must both be set or both be empty.', 1794600001);
        }
    }

    /**
     * Creates the settings from a task definition, e.g.
     * `idMap: {namespace: pages, sourceIdField: uid}`, optionally with
     * `targetIdField`.
     *
     * @param array<mixed> $settings
     * @throws InvalidConfigurationException
     */
    public static function fromArray(array $settings): self
    {
        $namespace = $settings['namespace'] ?? null;
        $sourceIdField = $settings['sourceIdField'] ?? null;
        if (!\is_string($namespace) || $namespace === '' || !\is_string($sourceIdField) || $sourceIdField === '') {
            throw new InvalidConfigurationException('idMap requires the strings namespace and sourceIdField.', 1794600002);
        }
        $targetIdField = $settings['targetIdField'] ?? '';
        if (!\is_string($targetIdField)) {
            throw new InvalidConfigurationException('idMap: targetIdField must be a string.', 1794600003);
        }

        return new self($namespace, $sourceIdField, $targetIdField);
    }

    /**
     * Fields left out of the checksum besides the source ID field
     *
     * @return list<string>
     */
    public function excludedFields(): array
    {
        return $this->targetIdField === '' ? [] : [$this->targetIdField];
    }

    public function isEnabled(): bool
    {
        return $this->namespace !== '';
    }
}
