<?php

declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Abstract base for all table repositories.
 *
 * Concrete repositories must declare the $table property and may override
 * any method to add domain-specific logic.
 *
 * Usage:
 *   final class UserRepository extends Repository
 *   {
 *       protected string $table = 'users';
 *   }
 */
abstract class Repository
{
    /**
     * The database table name managed by this repository.
     * Must be set in every concrete subclass.
     */
    abstract protected function getTable(): string;

    public function __construct(
        protected readonly DatabaseService $db,
    ) {
    }

    // ------------------------------------------------------------------
    //  Core query helpers
    // ------------------------------------------------------------------

    /**
     * Returns a fresh Selection for this repository's table.
     * Use this as the starting point for complex queries in subclasses.
     */
    protected function selection(): Selection
    {
        return $this->db->table($this->getTable());
    }

    // ------------------------------------------------------------------
    //  Basic CRUD
    // ------------------------------------------------------------------

    /**
     * Fetches all rows (unfiltered).
     */
    public function findAll(): Selection
    {
        return $this->selection();
    }

    /**
     * Fetches a single row by its primary key.
     * Returns null when the row does not exist.
     */
    public function findById(int $id): ?ActiveRow
    {
        return $this->db->find($this->getTable(), $id);
    }

    /**
     * Fetches rows matching arbitrary column conditions.
     *
     * Example:
     *   $repo->findBy(['role' => 'admin', 'city' => 'Praha'])
     */
    public function findBy(array $conditions): Selection
    {
        return $this->selection()->where($conditions);
    }

    /**
     * Fetches the first row matching the given conditions, or null.
     */
    public function findOneBy(array $conditions): ?ActiveRow
    {
        $row = $this->selection()->where($conditions)->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }

    /**
     * Inserts a new row and returns the created ActiveRow.
     *
     * @param  array<string, mixed>  $data  Column → value map
     */
    public function insert(array $data): ActiveRow
    {
        /** @var ActiveRow $row */
        $row = $this->selection()->insert($data);

        return $row;
    }

    /**
     * Updates the row with the given primary key.
     * Returns the number of affected rows (0 or 1).
     */
    public function update(int $id, array $data): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update($data);
    }

    /**
     * Deletes the row with the given primary key.
     * Returns the number of deleted rows.
     */
    public function delete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->delete();
    }

    // ------------------------------------------------------------------
    //  Counting
    // ------------------------------------------------------------------

    /**
     * Returns the total number of rows in the table.
     */
    public function count(): int
    {
        return $this->selection()->count('*');
    }

    /**
     * Returns the number of rows matching the given conditions.
     */
    public function countBy(array $conditions): int
    {
        return $this->selection()->where($conditions)->count('*');
    }

    // ------------------------------------------------------------------
    //  Existence checks
    // ------------------------------------------------------------------

    /**
     * Returns true when at least one row matches all given conditions.
     */
    public function existsBy(array $conditions): bool
    {
        return $this->countBy($conditions) > 0;
    }
}
