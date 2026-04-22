<?php

declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class DatabaseService
{
    public function __construct(
        private readonly Explorer $explorer,
    ) {
    }

    /** @return Selection<ActiveRow> */
    public function table(string $table): Selection
    {
        return $this->explorer->table($table);
    }

    public function find(string $table, int|string $id): ?ActiveRow
    {
        $row = $this->explorer->table($table)->get($id);

        return $row instanceof ActiveRow ? $row : null;
    }

    /**
     * @param literal-string $sql
     * @param mixed ...$params PDO-style positional bindings
     */
    public function query(string $sql, mixed ...$params): \Nette\Database\ResultSet
    {
        return $this->explorer->query($sql, ...$params);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return $this->explorer->transaction($callback);
    }

    public function getExplorer(): Explorer
    {
        return $this->explorer;
    }
}
