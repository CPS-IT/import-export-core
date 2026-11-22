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

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

/**
 * Behaves like the query builder of TYPO3: quotes the names given to
 * select(), from(), update(), set() and orderBy(), and adds a restriction to
 * each select query (like the default restrictions of TYPO3).
 */
class RestrictingQueryBuilder extends QueryBuilder
{
    public const string RESTRICTION = '1 = 0';

    public function __construct(private readonly QuotingConnection $quotingConnection)
    {
        parent::__construct($quotingConnection);
    }

    public function select(string ...$expressions): self
    {
        return parent::select(...array_map($this->quotingConnection->quoteName(...), $expressions));
    }

    public function addSelect(string $expression, string ...$expressions): self
    {
        return parent::addSelect($this->quotingConnection->quoteName($expression), ...array_map($this->quotingConnection->quoteName(...), $expressions));
    }

    public function from(string $table, ?string $alias = null): self
    {
        return parent::from($this->quotingConnection->quoteName($table), $alias);
    }

    public function update(string $table): self
    {
        return parent::update($this->quotingConnection->quoteName($table));
    }

    public function insert(string $table): self
    {
        return parent::insert($this->quotingConnection->quoteName($table));
    }

    public function delete(string $table): self
    {
        return parent::delete($this->quotingConnection->quoteName($table));
    }

    public function set(string $key, string $value): self
    {
        return parent::set($this->quotingConnection->quoteName($key), $value);
    }

    public function orderBy(string $sort, ?string $order = null): self
    {
        return parent::orderBy($this->quotingConnection->quoteName($sort), $order);
    }

    public function addOrderBy(string $sort, ?string $order = null): self
    {
        return parent::addOrderBy($this->quotingConnection->quoteName($sort), $order);
    }

    public function executeQuery(): Result
    {
        $this->andWhere(self::RESTRICTION);

        return parent::executeQuery();
    }
}
