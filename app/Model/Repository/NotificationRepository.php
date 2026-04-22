<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;

final class NotificationRepository extends Repository
{
    protected function getTable(): string
    {
        return 'notifications';
    }

    /** @return ActiveRow[] */
    public function findByUser(int $userId, int $offset = 0, int $limit = 20): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->order('created_at DESC')
            ->limit(max(0, $limit), max(0, $offset))
            ->fetchAll();
    }

    /** @return ActiveRow[] */
    public function findRecent(int $userId, int $limit = 5): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->order('created_at DESC')
            ->limit(max(0, $limit))
            ->fetchAll();
    }

    /** @return ActiveRow[] */
    public function findUnread(int $userId): array
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->order('created_at DESC')
            ->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->count('*');
    }

    public function countByUser(int $userId): int
    {
        return $this->selection()
            ->where('user_id', $userId)
            ->count('*');
    }

    public function markAsRead(int $id, int $userId): void
    {
        $this->selection()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => 1]);
    }

    public function markAllAsRead(int $userId): void
    {
        $this->selection()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }
}
