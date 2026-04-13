<?php

declare(strict_types=1);

namespace App\Core;

use App\Security\RoleHelper;
use Nette\Application\UI\Presenter;

/**
 * Abstract base for every presenter that requires an authenticated,
 * approved user.
 *
 * Access control is enforced in two layers inside startup():
 *
 *  1. Authentication — user must be logged in.
 *  2. Account status — identity must carry status === 'approved'.
 *     Pending/rejected sessions are destroyed and the user is sent
 *     to the login page.
 *  3. Role gate (optional) — if $requiredRole is set, the current
 *     user's role must meet or exceed that level in the hierarchy
 *     (guest < employee < support < admin).  On failure a flash
 *     message is set and the user is redirected to the homepage.
 *
 * All checks fire before any action/render method runs.
 */
abstract class SecuredPresenter extends Presenter
{
    /**
     * Set in a subclass to enforce a minimum role.
     *
     * Accepted values: 'guest' | 'employee' | 'support' | 'admin' | null
     * null = any approved user may access the presenter.
     */
    protected ?string $requiredRole = null;

    public RoleHelper $roleHelper;

    public function injectRoleHelper(RoleHelper $roleHelper): void
    {
        $this->roleHelper = $roleHelper;
    }

    public function startup(): void
    {
        parent::startup();

        $user = $this->getUser();

        // Layer 1 — must be logged in.
        if (!$user->isLoggedIn()) {
            $this->redirect(':Front:Auth:login');
        }

        // Layer 2 — account must be approved.
        // Guard against accounts downgraded after session establishment
        // (e.g. admin rejects a user who is already logged in).
        $status = (string) ($user->getIdentity()?->getData()['status'] ?? '');
        if ($status !== 'approved') {
            $user->logout(true);
            $this->redirect(':Front:Auth:login');
        }

        // Layer 3 — optional role gate.
        if ($this->requiredRole !== null && !$this->roleHelper->hasMinimumRole($this->requiredRole)) {
            $this->flashMessage('You do not have permission to view this page.', 'danger');
            $this->redirect(':Front:Homepage:default');
        }
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->user       = $this->getUser();
        $this->template->roleHelper = $this->roleHelper;
    }
}
