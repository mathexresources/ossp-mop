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

    public function findById(int $id): ?ActiveRow
    {
        $row = $this->selection()
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }

    /** @return Selection<ActiveRow> */
    public function findByTicket(int $ticketId): Selection
    {
        return $this->selection()
            ->where('ticket_id', $ticketId)
            ->where('deleted_at IS NULL')
            ->order('id ASC');
    }

    public function softDelete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update(['deleted_at' => new \DateTimeImmutable()]);
    }
}
