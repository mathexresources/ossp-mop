<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\UI\Presenter;

/**
 * Abstract base for every presenter that requires an authenticated,
 * approved user.
 *
 * Enforced in startup() so it fires before any action/render method.
 * Pending and rejected users are logged out and redirected to an
 * informational page rather than the login form, to give them a
 * clear explanation.
 */
abstract class SecuredPresenter extends Presenter
{
    public function startup(): void
    {
        parent::startup();

        $user = $this->getUser();

        if (!$user->isLoggedIn()) {
            $this->redirect(':Front:Auth:login');
        }

        // Guard against accounts that were downgraded after the session
        // was established (e.g. admin rejects a user who was already logged in).
        $status = (string) ($user->getIdentity()?->getData()['status'] ?? '');
        if ($status !== 'approved') {
            $user->logout(true);
            $this->redirect(':Front:Auth:login');
        }
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->user = $this->getUser();
    }
}
