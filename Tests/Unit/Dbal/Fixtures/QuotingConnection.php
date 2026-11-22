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

namespace CPSIT\ImportExportCore\Tests\Unit\Dbal\Fixtures;

use Doctrine\DBAL\Connection;

/**
 * Behaves like the connection of TYPO3: quotes table and column names in
 * insert(), update() and delete(), and returns a query builder that quotes
 * names and restricts the selected rows.
 */
class QuotingConnection extends Connection
{
    public function insert(string $table, array $data, array $types = []): int|string
    {
        return parent::insert($this->quoteName($table), $this->quoteKeys($data), $types);
    }

    public function update(string $table, array $data, array $criteria = [], array $types = []): int|string
    {
        return parent::update($this->quoteName($table), $this->quoteKeys($data), $this->quoteKeys($criteria), $types);
    }

    public function delete(string $table, array $criteria = [], array $types = []): int|string
    {
        return parent::delete($this->quoteName($table), $this->quoteKeys($criteria), $types);
    }

    public function createQueryBuilder(): RestrictingQueryBuilder
    {
        return new RestrictingQueryBuilder($this);
    }

    public function quoteName(string $name): string
    {
        if ($name === '*') {
            return $name;
        }

        return implode('.', array_map($this->quoteSingleIdentifier(...), explode('.', $name)));
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function quoteKeys(array $data): array
    {
        $quoted = [];
        foreach ($data as $key => $value) {
            $quoted[$this->quoteName((string)$key)] = $value;
        }

        return $quoted;
    }
}
