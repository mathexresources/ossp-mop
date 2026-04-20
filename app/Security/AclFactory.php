<?php

declare(strict_types=1);

namespace App\Security;

use Nette\Security\Permission;

/**
 * Builds the application ACL: roles, resources, and privileges.
 *
 * Role hierarchy (low → high):
 *   guest → employee → support → admin
 *
 * Each role inherits all privileges of every role below it.
 * Register this as a DI service and expose its create() method
 * as the Nette\Security\Permission service so the framework
 * auto-detects it as the application authorizator.
 *
 * To extend: add resources / privileges here only — presenters and
 * templates call $user->isAllowed($resource, $privilege) unchanged.
 */
final class AclFactory
{
    public function create(): Permission
    {
        $acl = new Permission();

        // ------------------------------------------------------------------
        //  Roles — ordered from least to most privileged.
        //  Each role inherits all allowed privileges of its parent.
        // ------------------------------------------------------------------
        $acl->addRole('guest');
        $acl->addRole('employee', 'guest');    // employee ⊃ guest
        $acl->addRole('support', 'employee'); // support  ⊃ employee ⊃ guest
        $acl->addRole('admin', 'support');  // admin    ⊃ support  ⊃ … ⊃ guest

        // ------------------------------------------------------------------
        //  Resources
        // ------------------------------------------------------------------
        $acl->addResource('ticket');
        $acl->addResource('service_history');
        $acl->addResource('user_management');
        $acl->addResource('item_management');
        $acl->addResource('notifications');
        $acl->addResource('admin_panel');
        $acl->addResource('support_tools');

        // ------------------------------------------------------------------
        //  Privileges
        //
        //  Rule: grant at the lowest role that first earns the privilege.
        //  Roles above inherit automatically via the hierarchy.
        // ------------------------------------------------------------------

        // ticket ─────────────────────────────────────────────────────────
        $acl->allow('guest', 'ticket', 'view');
        $acl->allow('employee', 'ticket', ['create', 'upload', 'edit']);
        $acl->allow('support', 'ticket', 'update-status');
        $acl->allow('admin', 'ticket', ['assign', 'delete']);

        // service_history ────────────────────────────────────────────────
        $acl->allow('support', 'service_history', 'add');

        // user_management ────────────────────────────────────────────────
        $acl->allow('admin', 'user_management', ['view', 'manage', 'approve', 'reject']);

        // item_management ────────────────────────────────────────────────
        $acl->allow('admin', 'item_management', ['view', 'manage']);

        // notifications ──────────────────────────────────────────────────
        $acl->allow('employee', 'notifications', 'view');

        // admin_panel ────────────────────────────────────────────────────
        $acl->allow('admin', 'admin_panel', 'access');

        // support_tools ──────────────────────────────────────────────────
        $acl->allow('support', 'support_tools', 'access');

        return $acl;
    }
}
