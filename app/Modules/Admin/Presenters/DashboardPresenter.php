<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\DatabaseService;

/**
 * Admin dashboard — overview of system-wide statistics.
 *
 * UserRepository is available via $this->userRepository, injected by
 * Admin\BasePresenter.  Only DatabaseService needs an additional inject
 * here for the raw ticket-count query.
 */
final class DashboardPresenter extends BasePresenter
{
    private DatabaseService $db;

    public function injectDatabaseService(DatabaseService $db): void
    {
        $this->db = $db;
    }

    public function renderDefault(): void
    {
        $this->template->title = 'Admin Dashboard';

        // User statistics — via UserRepository inherited from BasePresenter.
        $this->template->usersByRole   = $this->userRepository->countByRole();
        $this->template->usersByStatus = $this->userRepository->countByStatus();
        $this->template->pendingCount  = $this->userRepository->countPending();

        // Ticket statistics — counts only, no ticket business logic yet.
        $ticketRows = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status',
        )->fetchPairs('status', 'cnt');

        $this->template->ticketsByStatus = array_merge(
            ['open' => 0, 'in_progress' => 0, 'closed' => 0],
            $ticketRows,
        );

        $this->template->totalTickets = array_sum($this->template->ticketsByStatus);
        $this->template->totalUsers   = array_sum($this->template->usersByRole);
    }
}
