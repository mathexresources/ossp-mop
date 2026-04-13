<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

final class DashboardPresenter extends BasePresenter
{
    public function renderDefault(): void
    {
        $this->template->title = 'Admin Dashboard';
    }
}
