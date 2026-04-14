<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Repository\EmailLogRepository;
use Nette\Utils\Paginator;

/**
 * Read-only email log viewer for the Admin module.
 *
 * Displays a paginated, newest-first list of every email attempt
 * (sent or failed) recorded by MailService.  Useful for debugging
 * delivery issues in development and for auditing in production.
 */
final class EmailLogPresenter extends BasePresenter
{
    private const ITEMS_PER_PAGE = 25;

    private EmailLogRepository $emailLog;

    public function injectEmailLog(EmailLogRepository $emailLog): void
    {
        $this->emailLog = $emailLog;
    }

    public function renderDefault(int $page = 1): void
    {
        $total = $this->emailLog->countAll();

        $paginator = new Paginator();
        $paginator->setItemCount($total);
        $paginator->setItemsPerPage(self::ITEMS_PER_PAGE);
        $paginator->setPage($page);

        $this->template->title     = 'Email Log';
        $this->template->emails    = $this->emailLog->findAllDesc()
            ->limit($paginator->getLength(), $paginator->getOffset());
        $this->template->paginator = $paginator;
        $this->template->total     = $total;
    }
}
