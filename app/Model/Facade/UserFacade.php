<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Mail\MockMailer;
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
        private readonly UserRepository       $userRepository,
        private readonly MockMailer           $mailer,
        private readonly NotificationFacade   $notificationFacade,
    ) {
    }

    // ------------------------------------------------------------------
    //  Self-registration (pending → admin approves)
    // ------------------------------------------------------------------

    /**
     * Registers a new user with status = pending and role = employee.
     * Notifies every admin after insertion.
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

        $this->notifyAdminsNewUser($row);

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
     * Replaces a user's password with a newly hashed one.
     * Never stores the plain-text password.
     */
    public function resetPassword(int $userId, string $newPassword): void
    {
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
     * Approves a pending user account and notifies the user.
     */
    public function approve(int $userId): void
    {
        $user = $this->requireUser($userId);
        $this->userRepository->updateStatus($userId, 'approved');

        $this->mailer->send(
            $user->email,
            'Your account has been approved — OSSP MOP',
            "Hello {$user->first_name},\n\n"
                . "Your account has been approved. You can now log in at the OSSP MOP portal.\n\n"
                . "Regards,\nOSSP MOP Administration",
        );

        $this->notificationFacade->notify(
            $userId,
            NotificationFacade::TYPE_USER_APPROVED,
            'Your account has been approved. You can now log in.',
            '/auth/login',
        );
    }

    /**
     * Rejects a pending user account and notifies the user.
     * An optional reason is appended to the notification message.
     */
    public function reject(int $userId, ?string $reason = null): void
    {
        $user = $this->requireUser($userId);
        $this->userRepository->updateStatus($userId, 'rejected');

        $reasonText = ($reason !== null && trim($reason) !== '')
            ? "\n\nReason: " . trim($reason)
            : '';

        $this->mailer->send(
            $user->email,
            'Your account registration — OSSP MOP',
            "Hello {$user->first_name},\n\n"
                . "Unfortunately your account registration has been rejected.{$reasonText}\n\n"
                . "If you believe this is a mistake, please contact support.\n\n"
                . "Regards,\nOSSP MOP Administration",
        );

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

    private function notifyAdminsNewUser(ActiveRow $newUser): void
    {
        $admins = $this->userRepository->findByRole('admin');

        foreach ($admins as $admin) {
            $this->mailer->send(
                $admin->email,
                'New user pending approval — OSSP MOP',
                "Hello {$admin->first_name},\n\n"
                    . "A new user has registered and is waiting for your approval:\n\n"
                    . "  Name:  {$newUser->first_name} {$newUser->last_name}\n"
                    . "  Email: {$newUser->email}\n\n"
                    . "Please log in to the admin panel to approve or reject the account.\n\n"
                    . "Regards,\nOSSP MOP System",
            );

            $this->notificationFacade->notify(
                (int) $admin->id,
                NotificationFacade::TYPE_USER_PENDING,
                "{$newUser->first_name} {$newUser->last_name} ({$newUser->email}) has registered and is awaiting approval.",
                '/admin/user-approval',
            );
        }
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
