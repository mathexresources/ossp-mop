<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\Selection;

final class ItemRepository extends Repository
{
    protected function getTable(): string
    {
        return 'items';
    }

    /**
     * Returns items with optional filters and search.
     * Ordered by name ascending.
     *
     * @param int    $typeId     Filter by item_type_id (0 = no filter)
     * @param int    $locationId Filter by location_id (0 = no filter)
     * @param string $search     Partial match on name
     */
    public function findAllFiltered(int $typeId = 0, int $locationId = 0, string $search = ''): Selection
    {
        $q = $this->selection()->order('name ASC');

        if ($typeId > 0) {
            $q->where('item_type_id', $typeId);
        }

        if ($locationId > 0) {
            $q->where('location_id', $locationId);
        }

        if ($search !== '') {
            $q->where('name LIKE ?', "%{$search}%");
        }

        return $q;
    }

    /**
     * Returns true when at least one ticket references this item.
     * Used to guard hard-deletes.
     */
    public function hasTickets(int $id): bool
    {
        return $this->db->table('tickets')->where('item_id', $id)->count('*') > 0;
    }
}
