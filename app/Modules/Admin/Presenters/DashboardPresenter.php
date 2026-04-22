<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\DatabaseService;

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

        // ── User statistics ──────────────────────────────────────────
        $this->template->usersByRole   = $this->userRepository->countByRole();
        $this->template->usersByStatus = $this->userRepository->countByStatus();
        $this->template->pendingCount  = $this->userRepository->countPending();
        $this->template->totalUsers    = array_sum($this->userRepository->countByRole());

        // ── Ticket statistics ────────────────────────────────────────
        /** @var array<string, int> $ticketRows */
        $ticketRows = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM tickets WHERE deleted_at IS NULL GROUP BY status',
        )->fetchPairs('status', 'cnt');

        /** @var array<string, int> $ticketsByStatus */
        $ticketsByStatus = array_merge(
            ['open' => 0, 'in_progress' => 0, 'closed' => 0],
            $ticketRows,
        );

        $this->template->ticketsByStatus = $ticketsByStatus;
        $this->template->totalTickets    = array_sum($ticketsByStatus);

        // ── Item statistics ──────────────────────────────────────────
        $totalItemsRaw = $this->db->query('SELECT COUNT(*) FROM items')->fetchField();
        $this->template->totalItems = is_numeric($totalItemsRaw) ? (int) $totalItemsRaw : 0;

        // ── Recent activity: last 5 tickets ─────────────────────────
        $this->template->recentTickets = $this->db->query(
            'SELECT t.id, t.title, t.status, t.created_at,
                    u.first_name, u.last_name
             FROM tickets t
             LEFT JOIN users u ON t.created_by = u.id
             WHERE t.deleted_at IS NULL
             ORDER BY t.created_at DESC
             LIMIT 5',
        )->fetchAll();
    }
}
