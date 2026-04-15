<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

final class HomepagePresenter extends BasePresenter
{
    public function renderDefault(): void
    {
        $this->template->title = 'Home';
    }
}
