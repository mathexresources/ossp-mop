<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;

/**
 * Support presenter — support role and above only.
 *
 * $requiredRole = 'support' is enforced by SecuredPresenter::startup()
 * before any action or render method fires.  Employees and guests
 * receive a flash message and are redirected to the homepage.
 *
 * This presenter serves as the entry point for support-specific tools:
 * updating ticket statuses and adding service history records.
 * Full implementation is delivered in the ticket / service-history session.
 */
final class SupportPresenter extends SecuredPresenter
{
    protected ?string $requiredRole = 'support';

    // ------------------------------------------------------------------
    //  Support tools dashboard
    // ------------------------------------------------------------------

    public function renderDefault(): void
    {
        $this->template->title           = 'Support Tools';
        $this->template->canUpdateStatus = $this->getUser()->isAllowed('ticket', 'update-status');
        $this->template->canAddHistory   = $this->getUser()->isAllowed('service_history', 'add');
    }
}
