<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\RowType;
use App\Model\Repository\NotificationRepository;
use App\Model\Repository\UserRepository;

/**
 * Single point of notification creation and management.
 *
 * All other facades call this class — never write directly to the
 * notifications table from outside this facade.
 *
 * Type constants live here so callers never need to use raw strings.
 */
final class NotificationFacade
{
    // ------------------------------------------------------------------
    //  Notification type constants
    // ------------------------------------------------------------------

    public const TYPE_USER_PENDING           = 'user_pending';
    public const TYPE_USER_APPROVED          = 'user_approved';
    public const TYPE_USER_REJECTED          = 'user_rejected';
    public const TYPE_TICKET_CREATED         = 'ticket_created';
    public const TYPE_TICKET_ASSIGNED        = 'ticket_assigned';
    public const TYPE_TICKET_STATUS_CHANGED  = 'ticket_status_changed';
    public const TYPE_DAMAGE_POINT_ADDED     = 'damage_point_added';
    public const TYPE_IMAGE_ADDED            = 'image_added';
    public const TYPE_SERVICE_HISTORY_ADDED  = 'service_history_added';

    // ------------------------------------------------------------------

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly UserRepository         $userRepository,
    ) {
    }

    // ------------------------------------------------------------------
    //  Creation
    // ------------------------------------------------------------------

    /**
     * Creates a notification for a single user.
     * Failures are silently swallowed so the primary operation is never aborted.
     */
    public function notify(int $userId, string $type, string $message, ?string $linkUrl = null): void
    {
        try {
            $this->notificationRepository->insert([
                'user_id'    => $userId,
                'type'       => $type,
                'message'    => $message,
                'link_url'   => $linkUrl,
                'is_read'    => 0,
                'created_at' => new \DateTimeImmutable(),
            ]);
        } catch (\Throwable) {
            // Notification failure must not abort the calling operation.
        }
    }

    /**
     * Creates notifications for multiple users at once.
     * Duplicate user IDs are deduplicated before insertion.
     *
     * @param int[] $userIds
     */
    public function notifyMany(array $userIds, string $type, string $message, ?string $linkUrl = null): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->notify((int) $userId, $type, $message, $linkUrl);
        }
    }

    /**
     * Creates notifications for every non-deleted user with a given role.
     */
    public function notifyRole(string $role, string $type, string $message, ?string $linkUrl = null): void
    {
        $ids = [];
        foreach ($this->userRepository->findByRole($role) as $user) {
            $ids[] = RowType::int($user->id);
        }
        $this->notifyMany($ids, $type, $message, $linkUrl);
    }

    // ------------------------------------------------------------------
    //  Read / mark-as-read
    // ------------------------------------------------------------------

    /**
     * Marks a single notification as read.
     * The $userId guard prevents users from marking each other's notifications.
     */
    public function markAsRead(int $notificationId, int $userId): void
    {
        $this->notificationRepository->markAsRead($notificationId, $userId);
    }

    /**
     * Marks all of a user's notifications as read.
     */
    public function markAllAsRead(int $userId): void
    {
        $this->notificationRepository->markAllAsRead($userId);
    }

    // ------------------------------------------------------------------
    //  Queries
    // ------------------------------------------------------------------

    /**
     * Returns the number of unread notifications for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->notificationRepository->countUnread($userId);
    }

    /**
     * Returns all notifications for a user (newest first), with pagination.
     *
     * @return \Nette\Database\Table\ActiveRow[]
     */
    public function getAll(int $userId, int $offset = 0, int $limit = 20): array
    {
        return $this->notificationRepository->findByUser($userId, $offset, $limit);
    }

    /**
     * Returns total notification count for a user (needed for paginator).
     */
    public function countAll(int $userId): int
    {
        return $this->notificationRepository->countByUser($userId);
    }

    /**
     * Returns all unread notifications for a user, newest first.
     *
     * @return \Nette\Database\Table\ActiveRow[]
     */
    public function getUnread(int $userId): array
    {
        return $this->notificationRepository->findUnread($userId);
    }

    /**
     * Returns the $limit most recent unread notifications for a user.
     *
     * @return \Nette\Database\Table\ActiveRow[]
     */
    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->notificationRepository->findRecent($userId, $limit);
    }
}
