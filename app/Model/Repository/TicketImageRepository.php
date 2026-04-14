<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class TicketImageRepository extends Repository
{
    protected function getTable(): string
    {
        return 'ticket_images';
    }

    /**
     * Overrides base findById to exclude soft-deleted images.
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
     * Returns non-deleted images for a ticket, ordered by id ascending.
     */
    public function findByTicket(int $ticketId): Selection
    {
        return $this->selection()
            ->where('ticket_id', $ticketId)
            ->where('deleted_at IS NULL')
            ->order('id ASC');
    }

    /**
     * Soft-deletes an image. Returns number of affected rows.
     */
    public function softDelete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update(['deleted_at' => new \DateTimeImmutable()]);
    }
}
