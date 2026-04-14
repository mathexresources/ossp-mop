<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class TicketRepository extends Repository
{
    protected function getTable(): string
    {
        return 'tickets';
    }

    /**
     * Overrides base findById to exclude soft-deleted tickets.
     */
    public function findById(int $id): ?ActiveRow
    {
        $row = $this->selection()
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }

    /**
     * Returns non-deleted tickets with optional status / item / title filters.
     * Ordered newest first.
     */
    public function findAllFiltered(string $status = '', int $itemId = 0, string $search = ''): Selection
    {
        $q = $this->selection()
            ->where('deleted_at IS NULL')
            ->order('created_at DESC');

        if ($status !== '') {
            $q->where('status', $status);
        }

        if ($itemId > 0) {
            $q->where('item_id', $itemId);
        }

        if ($search !== '') {
            $q->where('title LIKE ?', "%{$search}%");
        }

        return $q;
    }

    /**
     * Tickets created by a specific user (non-deleted).
     */
    public function findByCreator(int $userId): Selection
    {
        return $this->selection()
            ->where('created_by', $userId)
            ->where('deleted_at IS NULL')
            ->order('created_at DESC');
    }

    /**
     * Tickets assigned to a specific user (non-deleted).
     */
    public function findByAssigned(int $userId): Selection
    {
        return $this->selection()
            ->where('assigned_to', $userId)
            ->where('deleted_at IS NULL')
            ->order('created_at DESC');
    }

    /**
     * Returns non-deleted, non-closed tickets for a specific item.
     * Used to find tickets to notify when a service record is added.
     */
    public function findActiveByItem(int $itemId): Selection
    {
        return $this->selection()
            ->where('item_id', $itemId)
            ->where('deleted_at IS NULL')
            ->where('status != ?', 'closed');
    }

    /**
     * Soft-deletes a ticket. Returns number of affected rows.
     */
    public function softDelete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update(['deleted_at' => new \DateTimeImmutable()]);
    }
}
