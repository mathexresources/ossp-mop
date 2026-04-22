<?php

declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

abstract class Repository
{
    abstract protected function getTable(): string;

    public function __construct(
        protected readonly DatabaseService $db,
    ) {
    }

    /** @return Selection<ActiveRow> */
    protected function selection(): Selection
    {
        return $this->db->table($this->getTable());
    }

    /** @return Selection<ActiveRow> */
    public function findAll(): Selection
    {
        return $this->selection();
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->db->find($this->getTable(), $id);
    }

    /**
     * @param array<string, mixed> $conditions
     * @return Selection<ActiveRow>
     */
    public function findBy(array $conditions): Selection
    {
        return $this->selection()->where($conditions);
    }

    /** @param array<string, mixed> $conditions */
    public function findOneBy(array $conditions): ?ActiveRow
    {
        $row = $this->selection()->where($conditions)->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): ActiveRow
    {
        $row = $this->selection()->insert($data);

        if (!$row instanceof ActiveRow) {
            throw new \RuntimeException('Expected ActiveRow from insert.');
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update($data);
    }

    public function delete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->delete();
    }

    public function count(): int
    {
        return $this->selection()->count('*');
    }

    /** @param array<string, mixed> $conditions */
    public function countBy(array $conditions): int
    {
        return $this->selection()->where($conditions)->count('*');
    }

    /** @param array<string, mixed> $conditions */
    public function existsBy(array $conditions): bool
    {
        return $this->countBy($conditions) > 0;
    }
}
