<?php

declare(strict_types=1);

namespace App\Modules\Api\Presenters;

use App\Model\Facade\NotificationFacade;

/**
 * AJAX API endpoints for the notification system.
 *
 * Routes (via RouterFactory Api RouteList — api/<presenter>/<action>[/<id>]):
 *   GET  /api/notifications/unread-count   — { count: N }
 *   GET  /api/notifications/recent         — 5 most recent unread
 *   POST /api/notifications/mark-read/{id} — mark one notification as read
 *   POST /api/notifications/mark-all-read  — mark all as read
 *
 * Every endpoint requires:
 *   - X-Requested-With: XMLHttpRequest  (CSRF mitigation)
 *   - Authenticated session
 */
final class NotificationsPresenter extends BasePresenter
{
    private NotificationFacade $notificationFacade;

    public function injectNotificationFacade(NotificationFacade $facade): void
    {
        $this->notificationFacade = $facade;
    }

    // ==================================================================
    //  GET /api/notifications/unread-count
    //  Returns: { "count": N }
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
    //  Returns 5 most recent unread notifications as JSON objects.
    //  Each object: { id, type, message, link_url, time_ago, created_at }
    // ==================================================================

    public function actionRecent(): void
    {
        $this->requireXhr();
        $this->requireLogin();

        $userId       = (int) $this->getUser()->getId();
        $recent       = $this->notificationFacade->getRecent($userId, 5);
        $now          = new \DateTimeImmutable();
        $notifications = [];

        foreach ($recent as $row) {
            $createdAt = $row->created_at instanceof \DateTimeInterface
                ? $row->created_at
                : new \DateTimeImmutable((string) $row->created_at);

            $notifications[] = [
                'id'         => (int)    $row->id,
                'type'       => (string) $row->type,
                'message'    => (string) $row->message,
                'link_url'   => $row->link_url ? (string) $row->link_url : null,
                'time_ago'   => $this->timeAgo($createdAt, $now),
                'created_at' => $createdAt->format('c'),
            ];
        }

        $this->sendJson(['notifications' => $notifications]);
    }

    // ==================================================================
    //  POST /api/notifications/mark-read/{id}
    //  Marks a single notification as read.
    //  Returns: { "success": true }
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
    //  Marks every unread notification for the current user as read.
    //  Returns: { "success": true }
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

    /**
     * Returns a human-readable "X minutes ago" string.
     */
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
