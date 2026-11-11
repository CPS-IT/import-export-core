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

namespace CPSIT\ImportExportCore\Dbal;

use CPSIT\ImportExportCore\ConfigurableInterface;
use CPSIT\ImportExportCore\ConfigurableTrait;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Sets the IDs related to a record, read from a relation (MM) table, e.g. the
 * categories of an event. Combine with `ResolveReferencePreProcessor` to map
 * them to target IDs. Requires the package doctrine/dbal.
 *
 * Runs one query per record.
 *
 * Configuration:
 *
 * * `table` (string, required): relation table, optionally `schema.table`
 * * `targetField` (string, required): field that receives the IDs
 * * `connection` (string, default `default`)
 * * `idField` (string, default `uid`): field of the record holding its ID
 * * `localField` (string, default `uid_local`): column referring to the record
 * * `foreignField` (string, default `uid_foreign`): column with the related ID
 * * `sortField` (string, default `sorting`): order of the IDs; '' for none
 * * `where` (map of field => value): further conditions, e.g. `fieldname`
 * * `separator` (string): if set, the IDs are joined to a string; otherwise
 *   the field receives a list
 *
 * A record without ID gets an empty list (or '').
 */
final class RelationListPreProcessor implements PreProcessorInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    public function __construct(private readonly ConnectionProviderInterface $connectionProvider)
    {
    }

    public function isConfigurationValid(array $configuration): bool
    {
        if (!Identifier::isTable($configuration['table'] ?? null)) {
            return false;
        }
        foreach (['targetField' => null, 'idField' => 'uid', 'localField' => 'uid_local', 'foreignField' => 'uid_foreign'] as $key => $default) {
            if (!Identifier::isColumn($configuration[$key] ?? $default)) {
                return false;
            }
        }
        $sortField = $configuration['sortField'] ?? 'sorting';
        if ($sortField !== '' && !Identifier::isColumn($sortField)) {
            return false;
        }
        $connection = $configuration['connection'] ?? ConnectionProviderInterface::DEFAULT_CONNECTION;
        if (!\is_string($connection) || $connection === '') {
            return false;
        }
        $where = $configuration['where'] ?? [];
        if (!\is_array($where) || !Conditions::isValid($where)) {
            return false;
        }

        return !isset($configuration['separator']) || (\is_string($configuration['separator']) && $configuration['separator'] !== '');
    }

    public function process(array $record): array
    {
        $configuration = $this->configuration;
        /** @var string $targetField */
        $targetField = $configuration['targetField'];
        $separator = $configuration['separator'] ?? null;
        $id = $record[(string)($configuration['idField'] ?? 'uid')] ?? null;

        $ids = \is_scalar($id) && (string)$id !== '' ? $this->fetchIds((string)$id) : [];
        $record[$targetField] = \is_string($separator) ? implode($separator, $ids) : $ids;

        return $record;
    }

    /**
     * @return list<string>
     */
    private function fetchIds(string $id): array
    {
        $configuration = $this->configuration;
        /** @var string $connectionName */
        $connectionName = $configuration['connection'] ?? ConnectionProviderInterface::DEFAULT_CONNECTION;
        $connection = $this->connectionProvider->getConnection($connectionName);
        $foreignField = Identifier::quote($connection, (string)($configuration['foreignField'] ?? 'uid_foreign'));
        $query = $connection->createQueryBuilder()
            ->select($foreignField)
            ->from(Identifier::quote($connection, (string)$configuration['table']));
        /** @var array<string, mixed> $where */
        $where = $configuration['where'] ?? [];
        $localId = ctype_digit($id) ? (int)$id : $id;
        Conditions::apply($query, $connection, [(string)($configuration['localField'] ?? 'uid_local') => $localId] + $where);
        $sortField = (string)($configuration['sortField'] ?? 'sorting');
        if ($sortField !== '') {
            $query->orderBy(Identifier::quote($connection, $sortField));
        }
        $query->addOrderBy($foreignField);

        return array_map(
            static fn (mixed $value): string => \is_scalar($value) ? (string)$value : '',
            $query->executeQuery()->fetchFirstColumn()
        );
    }
}
