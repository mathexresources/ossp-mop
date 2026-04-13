<?php

declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Thin wrapper around Nette\Database\Explorer.
 *
 * Registered as a service in config/common.neon so that it can be
 * injected into presenters and repositories via constructor injection.
 */
final class DatabaseService
{
    public function __construct(
        private readonly Explorer $explorer,
    ) {
    }

    /**
     * Returns a Selection (query builder) for the given table.
     */
    public function table(string $table): Selection
    {
        return $this->explorer->table($table);
    }

    /**
     * Fetches a single row by primary key; returns null when not found.
     */
    public function find(string $table, int|string $id): ?ActiveRow
    {
        $row = $this->explorer->table($table)->get($id);

        return $row instanceof ActiveRow ? $row : null;
    }

    /**
     * Executes a raw SQL query and returns the result set.
     * Use only when the query builder cannot express the query.
     *
     * @param  mixed  ...$params  PDO-style positional bindings
     */
    public function query(string $sql, mixed ...$params): \Nette\Database\ResultSet
    {
        return $this->explorer->query($sql, ...$params);
    }

    /**
     * Wraps a callable in a database transaction.
     * Re-throws any exception and rolls back automatically.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return $this->explorer->transaction($callback);
    }

    /**
     * Exposes the underlying Explorer for cases where direct access is needed.
     */
    public function getExplorer(): Explorer
    {
        return $this->explorer;
    }
}
