<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

/**
 * AdminPresenter — demonstrates the admin-only access pattern.
 *
 * Protection is entirely inherited:
 *   Admin\Presenters\BasePresenter sets $requiredRole = 'admin' and
 *   additionally confirms $user->isAllowed('admin_panel', 'access')
 *   via the ACL, so this class needs no extra startup() logic.
 *
 * Reachable at /admin/admin (router: Admin module, Admin presenter).
 */
final class AdminPresenter extends BasePresenter
{
    public function renderDefault(): void
    {
        $this->template->title = 'Admin Overview';

        // Expose fine-grained ACL flags to the template so buttons /
        // sections can be shown or hidden without duplicating role logic.
        $user = $this->getUser();
        $this->template->canManageUsers  = $user->isAllowed('user_management', 'manage');
        $this->template->canManageItems  = $user->isAllowed('item_management', 'manage');
        $this->template->canApproveUsers = $user->isAllowed('user_management', 'approve');
    }
}
