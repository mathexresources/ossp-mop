<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class ServiceHistoryRepository extends Repository
{
    protected function getTable(): string
    {
        return 'service_history';
    }

    /** @return Selection<ActiveRow> */
    public function findByItem(int $itemId): Selection
    {
        return $this->selection()
            ->where('item_id', $itemId)
            ->order('created_at DESC');
    }

    public function addRecord(int $itemId, string $description, int $createdBy): ActiveRow
    {
        return $this->insert([
            'item_id'     => $itemId,
            'description' => trim($description),
            'created_at'  => new \DateTimeImmutable(),
            'created_by'  => $createdBy,
        ]);
    }
}
