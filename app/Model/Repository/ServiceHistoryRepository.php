<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Append-only repository for service history records.
 *
 * Records can never be updated or deleted through this repository
 * (the parent delete() / update() methods remain available for
 * emergency admin use but are not exposed via the facade).
 */
final class ServiceHistoryRepository extends Repository
{
    protected function getTable(): string
    {
        return 'service_history';
    }

    /**
     * Returns all service history records for an item, newest first.
     */
    public function findByItem(int $itemId): Selection
    {
        return $this->selection()
            ->where('item_id', $itemId)
            ->order('created_at DESC');
    }

    /**
     * Inserts a new service history record and returns it.
     * created_at is always set to now; it cannot be overridden by callers.
     */
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
