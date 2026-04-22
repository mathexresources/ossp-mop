<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Database\RowType;
use App\Model\Repository\UserRepository;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;

final class AuthService implements Authenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @throws AuthenticationException with codes:
     *   - Authenticator::INVALID_CREDENTIAL — wrong email or password
     *   - Authenticator::NOT_APPROVED       — pending / rejected account
     */
    public function authenticate(string $user, string $password): IIdentity
    {
        $row = $this->userRepository->findByEmail($user);

        $validPassword = $row !== null && password_verify($password, RowType::string($row->password_hash));

        if ($row === null || !$validPassword) {
            throw new AuthenticationException(
                'Invalid email or password.',
                self::INVALID_CREDENTIAL,
            );
        }

        $status = RowType::string($row->status);

        return match ($status) {
            'pending'  => throw new AuthenticationException('pending', self::NOT_APPROVED),
            'rejected' => throw new AuthenticationException('rejected', self::NOT_APPROVED),
            default    => new SimpleIdentity(
                RowType::int($row->id),
                RowType::string($row->role),
                [
                    'email'      => RowType::string($row->email),
                    'first_name' => RowType::string($row->first_name),
                    'last_name'  => RowType::string($row->last_name),
                    'status'     => $status,
                ],
            ),
        };
    }
}
