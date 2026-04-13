<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{
    public function renderDefault(): void
    {
        $this->template->appName = 'OSSP MOP';
        $this->template->title = 'Homepage';
        $this->template->phpVersion = PHP_VERSION;
    }
}
