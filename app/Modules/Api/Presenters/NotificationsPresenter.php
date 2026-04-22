<?php

declare(strict_types=1);

namespace App\Modules\Api\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\NotificationFacade;

final class NotificationsPresenter extends BasePresenter
{
    private NotificationFacade $notificationFacade;

    public function injectNotificationFacade(NotificationFacade $facade): void
    {
        $this->notificationFacade = $facade;
    }

    // ==================================================================
    //  GET /api/notifications/unread-count
    // ==================================================================

    public function actionUnreadCount(): void
    {
        $this->requireXhr();
        $this->requireLogin();

        $count = $this->notificationFacade->getUnreadCount((int) $this->getUser()->getId());
        $this->sendJson(['count' => $count]);
    }

    // ==================================================================
    //  GET /api/notifications/recent
    // ==================================================================

    public function actionRecent(): void
    {
        $this->requireXhr();
        $this->requireLogin();

        $userId        = (int) $this->getUser()->getId();
        $recent        = $this->notificationFacade->getRecent($userId, 5);
        $now           = new \DateTimeImmutable();
        $notifications = [];

        foreach ($recent as $row) {
            $createdAtRaw = $row->created_at;
            $createdAt    = $createdAtRaw instanceof \DateTimeInterface
                ? $createdAtRaw
                : new \DateTimeImmutable(is_string($createdAtRaw) ? $createdAtRaw : 'now');

            $linkUrl = RowType::nullableString($row->link_url);

            $notifications[] = [
                'id'         => RowType::int($row->id),
                'type'       => RowType::string($row->type),
                'message'    => RowType::string($row->message),
                'link_url'   => $linkUrl,
                'time_ago'   => $this->timeAgo($createdAt, $now),
                'created_at' => $createdAt->format('c'),
            ];
        }

        $this->sendJson(['notifications' => $notifications]);
    }

    // ==================================================================
    //  POST /api/notifications/mark-read/{id}
    // ==================================================================

    public function actionMarkRead(int $id): void
    {
        $this->requireXhr();
        $this->requirePost();
        $this->requireLogin();

        $this->notificationFacade->markAsRead($id, (int) $this->getUser()->getId());
        $this->sendJson(['success' => true]);
    }

    // ==================================================================
    //  POST /api/notifications/mark-all-read
    // ==================================================================

    public function actionMarkAllRead(): void
    {
        $this->requireXhr();
        $this->requirePost();
        $this->requireLogin();

        $this->notificationFacade->markAllAsRead((int) $this->getUser()->getId());
        $this->sendJson(['success' => true]);
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function timeAgo(\DateTimeInterface $then, \DateTimeInterface $now): string
    {
        $diff = $now->getTimestamp() - $then->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $m = (int) ($diff / 60);
            return $m === 1 ? '1 minute ago' : "{$m} minutes ago";
        }

        if ($diff < 86400) {
            $h = (int) ($diff / 3600);
            return $h === 1 ? '1 hour ago' : "{$h} hours ago";
        }

        $d = (int) ($diff / 86400);
        return $d === 1 ? '1 day ago' : "{$d} days ago";
    }
}
