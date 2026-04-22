<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class ItemTypeRepository extends Repository
{
    protected function getTable(): string
    {
        return 'item_types';
    }

    /** @return Selection<ActiveRow> */
    public function findAllOrdered(): Selection
    {
        return $this->selection()->order('name ASC');
    }

    /** @return array<int, string> */
    public function fetchPairsForSelect(): array
    {
        /** @var array<int, string> $pairs */
        $pairs = $this->selection()->order('name ASC')->fetchPairs('id', 'name');

        return $pairs;
    }

    public function hasItems(int $id): bool
    {
        return $this->db->table('items')->where('item_type_id', $id)->count('*') > 0;
    }

    public function nameExistsExcept(string $name, ?int $excludeId = null): bool
    {
        $q = $this->selection()->where('name', $name);

        if ($excludeId !== null) {
            $q->where('id != ?', $excludeId);
        }

        return $q->count('*') > 0;
    }
}
