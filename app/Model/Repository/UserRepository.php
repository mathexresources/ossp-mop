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
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Returns all users with the given status (pending | approved | rejected).
     */
    public function findByStatus(string $status): Selection
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Returns all users with the given role.
     */
    public function findByRole(string $role): Selection
    {
        return $this->findBy(['role' => $role]);
    }

    /**
     * Updates only the status column for a single user.
     * Returns the number of affected rows (0 or 1).
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Returns true when an account with the given email already exists.
     */
    public function emailExists(string $email): bool
    {
        return $this->existsBy(['email' => $email]);
    }
}
