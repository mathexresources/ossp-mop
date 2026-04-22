<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class EmailLogRepository extends Repository
{
    protected function getTable(): string
    {
        return 'email_log';
    }

    public function logEmail(
        string $recipient,
        string $subject,
        string $type,
        string $status,
        ?string $error,
    ): void {
        $this->selection()->insert([
            'recipient'     => $recipient,
            'subject'       => $subject,
            'type'          => $type,
            'status'        => $status,
            'error_message' => $error,
        ]);
    }

    /** @return Selection<ActiveRow> */
    public function findAllDesc(): Selection
    {
        return $this->selection()->order('created_at DESC');
    }

    public function countAll(): int
    {
        return $this->selection()->count('*');
    }
}
