<?php

declare(strict_types=1);

namespace App\Security;

use Nette\Security\User;

/**
 * Convenience wrapper around Nette\Security\User for role checks.
 *
 * Registered as a DI service and injected into presenters via
 * injectRoleHelper().  Passed to templates as $roleHelper so Latte
 * can call $roleHelper->isAdmin() etc. without raw isInRole() calls.
 *
 * Role hierarchy (lowest → highest):
 *   guest → employee → support → admin
 *
 * hasMinimumRole($role) returns true when the current user's role is
 * at least as privileged as the given role — use this for presenter-
 * level access gates via SecuredPresenter::$requiredRole.
 */
final class RoleHelper
{
    private const ROLE_PRIORITY = [
        'guest'    => 0,
        'employee' => 1,
        'support'  => 2,
        'admin'    => 3,
    ];

    public function __construct(
        private readonly User $user,
    ) {
    }

    // ------------------------------------------------------------------
    //  Boolean role checks
    // ------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->currentRolePriority() >= self::ROLE_PRIORITY['admin'];
    }

    public function isSupport(): bool
    {
        return $this->currentRolePriority() >= self::ROLE_PRIORITY['support'];
    }

    public function isEmployee(): bool
    {
        return $this->currentRolePriority() >= self::ROLE_PRIORITY['employee'];
    }

    public function isGuest(): bool
    {
        return $this->getRole() === 'guest';
    }

    // ------------------------------------------------------------------
    //  Generic role-hierarchy check
    // ------------------------------------------------------------------

    /**
     * Returns true when the authenticated user's role is at least as
     * privileged as $minimumRole.
     *
     * Example:
     *   hasMinimumRole('support') → true for support AND admin
     */
    public function hasMinimumRole(string $minimumRole): bool
    {
        $required = self::ROLE_PRIORITY[$minimumRole] ?? 0;
        return $this->currentRolePriority() >= $required;
    }

    // ------------------------------------------------------------------
    //  Raw role string
    // ------------------------------------------------------------------

    /**
     * Returns the authenticated user's role string, or 'guest' when
     * no identity is present.
     */
    public function getRole(): string
    {
        if (!$this->user->isLoggedIn()) {
            return 'guest';
        }

        $roles = $this->user->getRoles();
        $role = $roles[0] ?? null;

        return is_string($role) ? $role : 'guest';
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    private function currentRolePriority(): int
    {
        if (!$this->user->isLoggedIn()) {
            return -1; // explicitly below guest
        }

        return self::ROLE_PRIORITY[$this->getRole()] ?? 0;
    }
}
