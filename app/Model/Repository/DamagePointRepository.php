<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Repository for ticket_damage_points.
 *
 * Damage points are soft-deleted (deleted_at column).
 * All reads automatically exclude soft-deleted rows.
 */
final class DamagePointRepository extends Repository
{
    protected function getTable(): string
    {
        return 'ticket_damage_points';
    }

    /**
     * Override findById to exclude soft-deleted rows.
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
     * Returns all active (non-deleted) damage points for a ticket, ordered by id.
     */
    public function findByTicket(int $ticketId): Selection
    {
        return $this->selection()
            ->where('ticket_id', $ticketId)
            ->where('deleted_at IS NULL')
            ->order('id ASC');
    }

    /**
     * Soft-deletes a damage point.
     */
    public function softDelete(int $id): void
    {
        $this->update($id, ['deleted_at' => new \DateTime()]);
    }
}
