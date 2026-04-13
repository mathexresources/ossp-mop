<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use Nette\Application\UI\Presenter;

/**
 * Base presenter for the Front module.
 *
 * Exposes the Nette User object to every template so the layout
 * can conditionally render auth nav links without needing additional
 * template variables set in each child presenter.
 */
abstract class BasePresenter extends Presenter
{
    public function beforeRender(): void
    {
        parent::beforeRender();

        // $presenter is always available in Latte, but making $user explicit
        // makes templates cleaner: {if $user->isLoggedIn()} …
        $this->template->user = $this->getUser();
        $this->template->title = 'OSSP MOP';
    }
}
