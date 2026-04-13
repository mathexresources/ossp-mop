<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Mail\MockMailer;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Handles all user-lifecycle business logic:
 * registration, admin approval / rejection, and related email notifications.
 */
final class UserFacade
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MockMailer     $mailer,
    ) {
    }

    // ------------------------------------------------------------------
    //  Registration
    // ------------------------------------------------------------------

    /**
     * Registers a new user with status = pending and role = employee.
     * Sends a notification e-mail to every admin after insertion.
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
            'phone'         => ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
            'birth_date'    => ($data['birth_date'] ?? '') !== '' ? $data['birth_date'] : null,
            'street'        => ($data['street'] ?? '') !== '' ? $data['street'] : null,
            'city'          => ($data['city'] ?? '') !== '' ? $data['city'] : null,
            'role'          => 'employee',
            'status'        => 'pending',
        ]);

        $this->notifyAdminsNewUser($row);

        return $row;
    }

    // ------------------------------------------------------------------
    //  Admin approval flow
    // ------------------------------------------------------------------

    /**
     * Approves a pending user account and notifies the user by e-mail.
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
    }

    /**
     * Rejects a pending user account and notifies the user by e-mail.
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
        }
    }
}
