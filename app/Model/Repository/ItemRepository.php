<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class ItemRepository extends Repository
{
    protected function getTable(): string
    {
        return 'items';
    }

    /** @return Selection<ActiveRow> */
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

    public function hasTickets(int $id): bool
    {
        return $this->db->table('tickets')->where('item_id', $id)->count('*') > 0;
    }
}
