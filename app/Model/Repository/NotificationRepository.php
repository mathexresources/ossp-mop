<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;

/**
 * Repository for the notifications table.
 *
 * All queries are scoped to a single user — notifications are private.
 */
final class NotificationRepository extends Repository
{
    protected function getTable(): string
    {
        return 'notifications';
    }

    /**
     * Returns a page of notifications for a user, newest first.
     *
     * @return ActiveRow[]
     */
    public function findByUser(int $userId, int $offset = 0, int $limit = 20): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->order('created_at DESC')
            ->limit($limit, $offset)
            ->fetchAll();
    }

    /**
     * Returns up to $limit most recent unread notifications for a user.
     *
     * @return ActiveRow[]
     */
    public function findRecent(int $userId, int $limit = 5): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->order('created_at DESC')
            ->limit($limit)
            ->fetchAll();
    }

    /**
     * Returns all unread notifications for a user, newest first.
     *
     * @return ActiveRow[]
     */
    public function findUnread(int $userId): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->order('created_at DESC')
            ->fetchAll();
    }

    /**
     * Counts unread notifications for a user.
     */
    public function countUnread(int $userId): int
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->count('*');
    }

    /**
     * Total notification count for a user (for pagination).
     */
    public function countByUser(int $userId): int
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->count('*');
    }

    /**
     * Marks a single notification as read.
     * The userId guard prevents users from reading each other's notifications.
     */
    public function markAsRead(int $id, int $userId): void
    {
        $this->selection()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => 1]);
    }

    /**
     * Marks all notifications for a user as read.
     */
    public function markAllAsRead(int $userId): void
    {
        $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }
}
