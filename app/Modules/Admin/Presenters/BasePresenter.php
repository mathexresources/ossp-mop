<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Core\SecuredPresenter;
use App\Model\Repository\UserRepository;

/**
 * Base presenter for the Admin module.
 *
 * Extends SecuredPresenter (login + approved-status + optional role
 * check) and adds a hard admin gate so every Admin presenter is
 * protected even if $requiredRole is not explicitly set.
 *
 * Also injects the pending-user count into the layout's nav badge
 * so every admin page shows how many registrations need attention.
 */
abstract class BasePresenter extends SecuredPresenter
{
    protected ?string $requiredRole = 'admin';

    public UserRepository $userRepository;

    public function injectUserRepository(UserRepository $userRepository): void
    {
        $this->userRepository = $userRepository;
    }

    public function startup(): void
    {
        parent::startup(); // enforces login + approved + role gate

        // Belt-and-suspenders: confirm ACL allows access to the admin panel.
        if (!$this->getUser()->isAllowed('admin_panel', 'access')) {
            $this->flashMessage('You do not have permission to view this page.', 'danger');
            $this->redirect(':Front:Homepage:default');
        }
    }

    public function beforeRender(): void
    {
        parent::beforeRender();

        // Expose pending count for the nav badge — available in @layout.latte.
        $this->template->pendingBadge = $this->userRepository->countPending();
    }
}
