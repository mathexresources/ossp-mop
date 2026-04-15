<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Mail\MailService;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Handles all user-lifecycle business logic:
 * registration, admin approval / rejection, admin-direct creation,
 * profile updates, password resets, and soft-deletion.
 */
final class UserFacade
{
    public function __construct(
        private readonly UserRepository     $userRepository,
        private readonly MailService        $mailService,
        private readonly NotificationFacade $notificationFacade,
    ) {
    }

    // ------------------------------------------------------------------
    //  Self-registration (pending → admin approves)
    // ------------------------------------------------------------------

    /**
     * Registers a new user with status = pending and role = employee.
     * Sends a welcome email to the new user and notifies every admin
     * via email and in-app notification.
     *
     * @param  array{
     *     first_name: string,
     *     last_name:  string,
     *     email:      string,
     *     password:   string,
     *     phone?:     string,
     *     birth_date?: string,
     *     street?:    string,
     *     city?:      string,
     * } $data
     */
    public function register(array $data): ActiveRow
    {
        $row = $this->userRepository->insert([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'phone'         => $this->nullable($data['phone'] ?? ''),
            'birth_date'    => $this->nullable($data['birth_date'] ?? ''),
            'street'        => $this->nullable($data['street'] ?? ''),
            'city'          => $this->nullable($data['city'] ?? ''),
            'role'          => 'employee',
            'status'        => 'pending',
        ]);

        // Email the new user a welcome / pending-approval notice.
        $this->mailService->sendWelcome($row);

        // Email all admins and create in-app notifications for each.
        $admins = $this->userRepository->findByRole('admin')
            ->where('status', 'approved')
            ->fetchAll();

        $this->mailService->sendAdminNewPending($row, $admins);

        foreach ($admins as $admin) {
            $this->notificationFacade->notify(
                (int) $admin->id,
                NotificationFacade::TYPE_USER_PENDING,
                "{$row->first_name} {$row->last_name} ({$row->email}) has registered and is awaiting approval.",
                '/admin/user-approval',
            );
        }

        return $row;
    }

    // ------------------------------------------------------------------
    //  Admin-direct user creation (pre-approved)
    // ------------------------------------------------------------------

    /**
     * Creates a user on behalf of an admin.
     * Status is set to approved immediately — no approval flow needed.
     *
     * @param  array{
     *     first_name: string,
     *     last_name:  string,
     *     email:      string,
     *     password:   string,
     *     role:       string,
     *     phone?:     string,
     *     birth_date?: string,
     *     street?:    string,
     *     city?:      string,
     * } $data
     */
    public function createByAdmin(array $data): ActiveRow
    {
        return $this->userRepository->insert([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => $data['role'],
            'status'        => 'approved',
            'phone'         => $this->nullable($data['phone'] ?? ''),
            'birth_date'    => $this->nullable($data['birth_date'] ?? ''),
            'street'        => $this->nullable($data['street'] ?? ''),
            'city'          => $this->nullable($data['city'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------
    //  Profile update (everything except password)
    // ------------------------------------------------------------------

    /**
     * Updates a user's profile fields and optionally their role / status.
     *
     * @param  array{
     *     first_name: string,
     *     last_name:  string,
     *     email:      string,
     *     role:       string,
     *     status:     string,
     *     phone?:     string,
     *     birth_date?: string,
     *     street?:    string,
     *     city?:      string,
     * } $data
     */
    public function update(int $userId, array $data): void
    {
        $this->userRepository->update($userId, [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'role'       => $data['role'],
            'status'     => $data['status'],
            'phone'      => $this->nullable($data['phone'] ?? ''),
            'birth_date' => $this->nullable($data['birth_date'] ?? ''),
            'street'     => $this->nullable($data['street'] ?? ''),
            'city'       => $this->nullable($data['city'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------
    //  Password management
    // ------------------------------------------------------------------

    /**
     * Replaces a user's password with a newly hashed one and notifies the
     * user by email that their password was changed by an administrator.
     */
    public function resetPassword(int $userId, string $newPassword): void
    {
        $this->userRepository->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);

        $user = $this->userRepository->findById($userId);
        if ($user !== null) {
            $this->mailService->sendPasswordChanged($user);
        }
    }

    // ------------------------------------------------------------------
    //  Self-service profile update
    // ------------------------------------------------------------------

    /**
     * Updates only the editable profile fields (no role / status changes).
     * Safe to call from a user's own profile page.
     *
     * @param  array{
     *     first_name: string,
     *     last_name:  string,
     *     phone?:     string,
     *     birth_date?: string,
     *     street?:    string,
     *     city?:      string,
     * } $data
     */
    public function updateProfile(int $userId, array $data): void
    {
        $this->userRepository->update($userId, [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'phone'      => $this->nullable($data['phone'] ?? ''),
            'birth_date' => $this->nullable($data['birth_date'] ?? ''),
            'street'     => $this->nullable($data['street'] ?? ''),
            'city'       => $this->nullable($data['city'] ?? ''),
        ]);
    }

    /**
     * Allows a user to change their own password.
     * Verifies the current password before updating.
     *
     * @throws \RuntimeException if the current password is incorrect
     */
    public function changeOwnPassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->requireUser($userId);

        if (!password_verify($currentPassword, (string) $user->password_hash)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $this->userRepository->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }

    // ------------------------------------------------------------------
    //  Soft deletion
    // ------------------------------------------------------------------

    /**
     * Soft-deletes a user by setting deleted_at to the current time.
     * The row is retained; the user can no longer log in or appear
     * in normal queries.
     */
    public function softDelete(int $userId): void
    {
        $this->userRepository->softDelete($userId);
    }

    // ------------------------------------------------------------------
    //  Admin approval flow
    // ------------------------------------------------------------------

    /**
     * Approves a pending user account, notifies the user by email,
     * and creates an in-app notification.
     */
    public function approve(int $userId): void
    {
        $user = $this->requireUser($userId);
        $this->userRepository->updateStatus($userId, 'approved');

        $this->mailService->sendApproved($user);

        $this->notificationFacade->notify(
            $userId,
            NotificationFacade::TYPE_USER_APPROVED,
            'Your account has been approved. You can now log in.',
            '/auth/login',
        );
    }

    /**
     * Rejects a pending user account, notifies the user by email (including
     * any reason), and creates an in-app notification.
     */
    public function reject(int $userId, ?string $reason = null): void
    {
        $user = $this->requireUser($userId);
        $this->userRepository->updateStatus($userId, 'rejected');

        $this->mailService->sendRejected($user, $reason);

        $reasonSuffix = ($reason !== null && trim($reason) !== '')
            ? ' Reason: ' . trim($reason)
            : '';

        $this->notificationFacade->notify(
            $userId,
            NotificationFacade::TYPE_USER_REJECTED,
            'Your account registration has been rejected.' . $reasonSuffix,
            '/auth/login',
        );
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    private function requireUser(int $userId): ActiveRow
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \RuntimeException("User #{$userId} not found.");
        }

        return $user;
    }

    /**
     * Returns null for empty / whitespace-only strings, otherwise the value.
     */
    private function nullable(mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value ?: null;
    }
}
