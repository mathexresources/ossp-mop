<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class UserRepository extends Repository
{
    protected function getTable(): string
    {
        return 'users';
    }

    public function findByEmail(string $email): ?ActiveRow
    {
        return $this->findOneBy(['email' => $email, 'deleted_at' => null]);
    }

    /** @return Selection<ActiveRow> */
    public function findByStatus(string $status): Selection
    {
        return $this->findBy(['status' => $status, 'deleted_at' => null]);
    }

    /** @return Selection<ActiveRow> */
    public function findByRole(string $role): Selection
    {
        return $this->findBy(['role' => $role, 'deleted_at' => null]);
    }

    /**
     * @param array{role?: string, status?: string} $filters
     * @return Selection<ActiveRow>
     */
    public function findAllForAdmin(array $filters = [], string $search = ''): Selection
    {
        $q = $this->selection()->where('deleted_at IS NULL');

        if (!empty($filters['role'])) {
            $q->where('role', $filters['role']);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if ($search !== '') {
            $q->where(
                'first_name LIKE ? OR last_name LIKE ? OR email LIKE ?',
                "%{$search}%",
                "%{$search}%",
                "%{$search}%",
            );
        }

        return $q->order('created_at DESC');
    }

    public function softDelete(int $id): int
    {
        return $this->selection()
            ->where('id', $id)
            ->update(['deleted_at' => new \DateTimeImmutable()]);
    }

    public function emailExistsExcept(string $email, ?int $excludeId = null): bool
    {
        $q = $this->selection()
            ->where('email', $email)
            ->where('deleted_at IS NULL');

        if ($excludeId !== null) {
            $q->where('id != ?', $excludeId);
        }

        return $q->count('*') > 0;
    }

    /** @return array<string, int> */
    public function countByRole(): array
    {
        $counts = [];
        foreach (['guest', 'employee', 'support', 'admin'] as $role) {
            $counts[$role] = $this->selection()
                ->where('role', $role)
                ->where('deleted_at IS NULL')
                ->count('*');
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $counts = [];
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $counts[$status] = $this->selection()
                ->where('status', $status)
                ->where('deleted_at IS NULL')
                ->count('*');
        }

        return $counts;
    }

    public function countPending(): int
    {
        return $this->selection()
            ->where('status', 'pending')
            ->where('deleted_at IS NULL')
            ->count('*');
    }

    public function updateStatus(int $id, string $status): int
    {
        return $this->update($id, ['status' => $status]);
    }

    public function emailExists(string $email): bool
    {
        return $this->emailExistsExcept($email);
    }
}
