<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\Selection;

final class ItemTypeRepository extends Repository
{
    protected function getTable(): string
    {
        return 'item_types';
    }

    /**
     * Returns all item types ordered alphabetically by name.
     */
    public function findAllOrdered(): Selection
    {
        return $this->selection()->order('name ASC');
    }

    /**
     * Returns an id → name map suitable for form select boxes.
     *
     * @return array<int, string>
     */
    public function fetchPairsForSelect(): array
    {
        return $this->selection()->order('name ASC')->fetchPairs('id', 'name');
    }

    /**
     * Returns true when at least one item is assigned to this type.
     */
    public function hasItems(int $id): bool
    {
        return $this->db->table('items')->where('item_type_id', $id)->count('*') > 0;
    }

    /**
     * Returns true if an item type with the given name already exists,
     * optionally ignoring a specific row (for edit validation).
     */
    public function nameExistsExcept(string $name, ?int $excludeId = null): bool
    {
        $q = $this->selection()->where('name', $name);

        if ($excludeId !== null) {
            $q->where('id != ?', $excludeId);
        }

        return $q->count('*') > 0;
    }
}
