<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
use Nette\Utils\Paginator;

/**
 * Full notification centre for the logged-in user.
 *
 * Actions:
 *   default — paginated list of all notifications with read/unread filter
 */
final class NotificationsPresenter extends SecuredPresenter
{
    protected ?string $requiredRole = null;

    private const PER_PAGE = 20;

    // $notificationFacade is inherited from SecuredPresenter (injected there).

    // ==================================================================
    //  default — notification centre
    // ==================================================================

    public function renderDefault(int $page = 1, string $filter = ''): void
    {
        $userId    = (int) $this->getUser()->getId();
        $paginator = new Paginator();
        $paginator->setItemsPerPage(self::PER_PAGE);
        $paginator->setPage(max(1, $page));

        if ($filter === 'unread') {
            $all   = $this->notificationFacade->getUnread($userId);
            $total = count($all);
            $paginator->setItemCount($total);
            $offset        = $paginator->getOffset();
            $notifications = array_slice($all, $offset, self::PER_PAGE);
        } else {
            $total = $this->notificationFacade->countAll($userId);
            $paginator->setItemCount($total);
            $notifications = $this->notificationFacade->getAll(
                $userId,
                $paginator->getOffset(),
                self::PER_PAGE,
            );
        }

        $this->template->title         = 'Notifications';
        $this->template->notifications = $notifications;
        $this->template->paginator     = $paginator;
        $this->template->filter        = $filter;
    }

    // ==================================================================
    //  handleMarkAllRead — "Mark all as read" button
    // ==================================================================

    public function handleMarkAllRead(): void
    {
        $this->notificationFacade->markAllAsRead((int) $this->getUser()->getId());
        $this->flashMessage('All notifications marked as read.', 'success');
        $this->redirect('this');
    }

    // ==================================================================
    //  handleMarkRead — click on a single notification
    //  Marks it as read then redirects to the stored link_url (if any).
    // ==================================================================

    public function handleMarkRead(int $id, string $redirect = ''): void
    {
        $userId = (int) $this->getUser()->getId();
        $this->notificationFacade->markAsRead($id, $userId);

        if ($redirect !== '') {
            $this->redirectUrl($redirect);
        }

        $this->redirect('this');
    }
}
