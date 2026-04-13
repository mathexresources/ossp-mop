<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Core\SecuredPresenter;

/**
 * Base presenter for the Admin module.
 *
 * Extends SecuredPresenter (login + approved-status + optional role
 * check) and adds a hard admin gate so every Admin presenter is
 * protected even if $requiredRole is not explicitly set.
 *
 * Uses $user->isAllowed() against the ACL so the authorizator is the
 * single source of truth for what "admin" may access.
 */
abstract class BasePresenter extends SecuredPresenter
{
    protected ?string $requiredRole = 'admin';

    public function startup(): void
    {
        parent::startup(); // enforces login + approved + role gate

        // Belt-and-suspenders: confirm ACL allows access to the admin panel.
        // This fires after parent::startup() so the user is guaranteed to be
        // logged in and approved at this point.
        if (!$this->getUser()->isAllowed('admin_panel', 'access')) {
            $this->flashMessage('You do not have permission to view this page.', 'danger');
            $this->redirect(':Front:Homepage:default');
        }
    }
}
