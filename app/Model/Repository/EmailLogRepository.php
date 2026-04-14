<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\Selection;

/**
 * Read/write repository for the email_log table.
 *
 * Every outgoing email (sent or failed) is recorded here so admins
 * can inspect delivery history from the Admin panel.
 */
final class EmailLogRepository extends Repository
{
    protected function getTable(): string
    {
        return 'email_log';
    }

    /**
     * Records a single email attempt.
     *
     * @param string      $recipient  Destination address
     * @param string      $subject    Email subject
     * @param string      $type       Internal mail type identifier (e.g. 'welcome')
     * @param string      $status     'sent' or 'failed'
     * @param string|null $error      Exception message on failure, null on success
     */
    public function logEmail(
        string  $recipient,
        string  $subject,
        string  $type,
        string  $status,
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

    /**
     * Returns all log entries newest-first, ready for pagination.
     */
    public function findAllDesc(): Selection
    {
        return $this->selection()->order('created_at DESC');
    }

    /**
     * Total number of log entries.
     */
    public function countAll(): int
    {
        return $this->selection()->count('*');
    }
}
