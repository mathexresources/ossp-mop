<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;

/**
 * Ticket presenter — demonstrates graduated RBAC.
 *
 * View  (default action) — any approved user, including guest role.
 *   $requiredRole = null so SecuredPresenter enforces login + approval
 *   only, and no further role check is applied.
 *
 * Create action — employee privilege required.
 *   Checked via the authorizator ($user->isAllowed) so the ACL is the
 *   single source of truth.  Guests land back on the ticket list with
 *   a clear flash message.
 *
 * Assign action — admin only (checked via ACL).
 * UpdateStatus action — support and above (checked via ACL).
 */
final class TicketPresenter extends SecuredPresenter
{
    // Any approved user may reach this presenter.
    // The create / assign / update-status actions add finer guards below.
    protected ?string $requiredRole = null;

    // ------------------------------------------------------------------
    //  View — all approved users
    // ------------------------------------------------------------------

    public function renderDefault(): void
    {
        $this->template->title        = 'Tickets';
        $this->template->canCreate    = $this->getUser()->isAllowed('ticket', 'create');
        $this->template->canAssign    = $this->getUser()->isAllowed('ticket', 'assign');
        $this->template->canUpdateStatus = $this->getUser()->isAllowed('ticket', 'update-status');
    }

    // ------------------------------------------------------------------
    //  Create — employee, support, admin
    // ------------------------------------------------------------------

    public function actionCreate(): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'create')) {
            $this->flashMessage('You do not have permission to create tickets.', 'danger');
            $this->redirect('default');
        }
    }

    public function renderCreate(): void
    {
        $this->template->title = 'Create Ticket';
    }

    // ------------------------------------------------------------------
    //  Assign — admin only
    // ------------------------------------------------------------------

    public function actionAssign(int $id): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'assign')) {
            $this->flashMessage('You do not have permission to assign tickets.', 'danger');
            $this->redirect('default');
        }

        // Ticket assignment logic goes here in a future session.
        $this->flashMessage("Ticket #{$id} assignment (stub — implement in ticket session).", 'info');
        $this->redirect('default');
    }

    // ------------------------------------------------------------------
    //  UpdateStatus — support and admin
    // ------------------------------------------------------------------

    public function actionUpdateStatus(int $id): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'update-status')) {
            $this->flashMessage('You do not have permission to update ticket status.', 'danger');
            $this->redirect('default');
        }

        // Status-update logic goes here in a future session.
        $this->flashMessage("Ticket #{$id} status update (stub — implement in ticket session).", 'info');
        $this->redirect('default');
    }
}
