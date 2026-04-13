<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Core\SecuredPresenter;

/**
 * Base presenter for the Admin module.
 *
 * Extends SecuredPresenter (login + approved-status check) and adds
 * a role gate so that only admin accounts can reach any admin presenter.
 */
abstract class BasePresenter extends SecuredPresenter
{
    public function startup(): void
    {
        parent::startup(); // enforces login + approved status first

        if (!$this->getUser()->isInRole('admin')) {
            $this->error('Access denied.', 403);
        }
    }
}
