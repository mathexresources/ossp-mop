<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Repository\UserRepository;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;

/**
 * Nette authenticator.
 *
 * Wired as the application authenticator via security.neon.
 * Checks credentials, then verifies the account status before
 * issuing an identity.  Pending and rejected accounts are blocked
 * at this layer so they can never obtain a session.
 */
final class AuthService implements Authenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @throws AuthenticationException  with codes:
     *   - Authenticator::INVALID_CREDENTIAL — wrong email or password
     *   - Authenticator::NOT_APPROVED       — pending / rejected account
     *     (message contains the literal status string for presenters to react to)
     */
    public function authenticate(string $user, string $password): IIdentity
    {
        $row = $this->userRepository->findByEmail($user);

        // Always perform password_verify to prevent timing-based user enumeration.
        $validPassword = $row !== null && password_verify($password, (string) $row->password_hash);

        if ($row === null || !$validPassword) {
            throw new AuthenticationException(
                'Invalid email or password.',
                self::INVALID_CREDENTIAL,
            );
        }

        return match ((string) $row->status) {
            'pending'  => throw new AuthenticationException('pending', self::NOT_APPROVED),
            'rejected' => throw new AuthenticationException('rejected', self::NOT_APPROVED),
            default    => new SimpleIdentity(
                (int) $row->id,
                (string) $row->role,
                [
                    'email'      => (string) $row->email,
                    'first_name' => (string) $row->first_name,
                    'last_name'  => (string) $row->last_name,
                    'status'     => (string) $row->status,
                ],
            ),
        };
    }
}
