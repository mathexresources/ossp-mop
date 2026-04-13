<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Security\RoleHelper;
use Nette\Application\UI\Presenter;

/**
 * Base presenter for the Front module.
 *
 * Exposes the Nette User object and the RoleHelper to every template
 * so the layout can conditionally render nav links without extra
 * template variables being set in each child presenter.
 */
abstract class BasePresenter extends Presenter
{
    public RoleHelper $roleHelper;

    public function injectRoleHelper(RoleHelper $roleHelper): void
    {
        $this->roleHelper = $roleHelper;
    }

    public function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->user       = $this->getUser();
        $this->template->roleHelper = $this->roleHelper;
        $this->template->title      = 'OSSP MOP';
    }
}
