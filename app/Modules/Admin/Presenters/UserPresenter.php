<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Facade\UserFacade;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Paginator;

/**
 * Full user-management presenter for the Admin module.
 *
 * UserRepository is provided by Admin\BasePresenter via injectUserRepository().
 * Only UserFacade is injected here.
 *
 * Actions:
 *   default   — paginated, filterable user list
 *   create    — admin creates an approved user directly
 *   edit      — edit profile, role, and status
 *   password  — set a new password for any user
 *   delete    — soft-delete with confirmation
 *
 * Pending-user approval is handled by UserApprovalPresenter.
 * Pending users are highlighted in the list with direct links to
 * the approval flow.
 *
 * Self-protection rules (enforced before any write):
 *   - Admin cannot delete their own account.
 *   - Admin cannot demote their own role away from 'admin'.
 *   - Admin cannot change their own status away from 'approved'.
 */
final class UserPresenter extends BasePresenter
{
    private const ITEMS_PER_PAGE = 15;

    /**
     * The user record being acted on (edit / password / delete).
     * Set by action*() methods before form components are created,
     * so handlers can use it without relying on a tamperable hidden field.
     */
    private ?ActiveRow $targetUser = null;

    private UserFacade $userFacade;

    public function injectUserFacade(UserFacade $userFacade): void
    {
        $this->userFacade = $userFacade;
    }

    // ==================================================================
    //  LIST
    // ==================================================================

    public function renderDefault(int $page = 1): void
    {
        $role   = (string) ($this->getParameter('role')   ?? '');
        $status = (string) ($this->getParameter('status') ?? '');
        $search = trim((string) ($this->getParameter('search') ?? ''));

        $filters = ['role' => $role, 'status' => $status];

        // Two calls — each returns a fresh lazy Selection; no clone needed.
        $total = $this->userRepository->findAllForAdmin($filters, $search)->count('*');

        $paginator = new Paginator();
        $paginator->setItemCount($total);
        $paginator->setItemsPerPage(self::ITEMS_PER_PAGE);
        $paginator->setPage($page);

        $users = $this->userRepository->findAllForAdmin($filters, $search)
            ->limit($paginator->getLength(), $paginator->getOffset());

        $this->template->title        = 'User Management';
        $this->template->users        = $users;
        $this->template->paginator    = $paginator;
        $this->template->filters      = $filters + ['search' => $search];
        $this->template->pendingCount = $this->userRepository->countPending();
    }

    // ==================================================================
    //  CREATE
    // ==================================================================

    public function renderCreate(): void
    {
        $this->template->title = 'Create User';
    }

    protected function createComponentCreateForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('first_name', 'First Name')
            ->setRequired('First name is required.')
            ->setMaxLength(80);

        $form->addText('last_name', 'Last Name')
            ->setRequired('Last name is required.')
            ->setMaxLength(80);

        $form->addEmail('email', 'Email Address')
            ->setRequired('Email address is required.')
            ->setMaxLength(180)
            ->addRule(
                fn ($input) => !$this->userRepository->emailExistsExcept($input->getValue()),
                'This email address is already in use.',
            );

        $form->addPassword('password', 'Password')
            ->setRequired('Password is required.')
            ->setMaxLength(255)
            ->addRule(Form::MinLength, 'Password must be at least 8 characters.', 8);

        $form->addSelect('role', 'Role', self::roleOptions())
            ->setRequired('Role is required.');

        $form->addText('phone', 'Phone')
            ->setMaxLength(30);

        $form->addText('birth_date', 'Date of Birth')
            ->setHtmlAttribute('type', 'date');

        $form->addText('street', 'Street')
            ->setMaxLength(180);

        $form->addText('city', 'City')
            ->setMaxLength(100);

        $form->addSubmit('submit', 'Create User')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'createFormSucceeded'];

        return $form;
    }

    public function createFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->userFacade->createByAdmin((array) $values);
            $this->flashMessage('User created successfully.', 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to create user: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  EDIT
    // ==================================================================

    public function actionEdit(int $id): void
    {
        $this->targetUser = $this->requireActiveUser($id);
    }

    public function renderEdit(int $id): void
    {
        $u = $this->targetUser;

        $this->template->title      = 'Edit User — ' . $u->first_name . ' ' . $u->last_name;
        $this->template->targetUser = $u;
        $this->template->isSelf     = ($u->id === (int) $this->getUser()->getId());

        $this['editForm']->setDefaults([
            'first_name' => $u->first_name,
            'last_name'  => $u->last_name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'birth_date' => $u->birth_date,
            'street'     => $u->street,
            'city'       => $u->city,
            'role'       => $u->role,
            'status'     => $u->status,
        ]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('first_name', 'First Name')
            ->setRequired('First name is required.')
            ->setMaxLength(80);

        $form->addText('last_name', 'Last Name')
            ->setRequired('Last name is required.')
            ->setMaxLength(80);

        $form->addEmail('email', 'Email Address')
            ->setRequired('Email address is required.')
            ->setMaxLength(180)
            ->addRule(
                fn ($input) => !$this->userRepository->emailExistsExcept(
                    $input->getValue(),
                    $this->targetUser?->id,
                ),
                'This email address is already in use.',
            );

        $form->addText('phone', 'Phone')
            ->setMaxLength(30);

        $form->addText('birth_date', 'Date of Birth')
            ->setHtmlAttribute('type', 'date');

        $form->addText('street', 'Street')
            ->setMaxLength(180);

        $form->addText('city', 'City')
            ->setMaxLength(100);

        $form->addSelect('role', 'Role', self::roleOptions())
            ->setRequired('Role is required.');

        $form->addSelect('status', 'Status', [
            'approved' => 'Approved',
            'pending'  => 'Pending',
            'rejected' => 'Rejected',
        ])->setRequired('Status is required.');

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $id     = $this->targetUser->id;
        $isSelf = ($id === (int) $this->getUser()->getId());

        // Self-protection: admin cannot demote themselves or lose approved status.
        if ($isSelf) {
            if ($values->role !== 'admin') {
                $form->addError('You cannot change your own role.');
                return;
            }
            if ($values->status !== 'approved') {
                $form->addError('You cannot change your own account status.');
                return;
            }
        }

        try {
            $this->userFacade->update($id, (array) $values);
            $this->flashMessage('User updated successfully.', 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to update user: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  PASSWORD RESET
    // ==================================================================

    public function actionPassword(int $id): void
    {
        $this->targetUser = $this->requireActiveUser($id);
    }

    public function renderPassword(int $id): void
    {
        $this->template->title      = 'Reset Password — ' . $this->targetUser->first_name . ' ' . $this->targetUser->last_name;
        $this->template->targetUser = $this->targetUser;
    }

    protected function createComponentPasswordForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addPassword('new_password', 'New Password')
            ->setRequired('New password is required.')
            ->setMaxLength(255)
            ->addRule(Form::MinLength, 'Password must be at least 8 characters.', 8);

        $form->addPassword('confirm_password', 'Confirm Password')
            ->setRequired('Please confirm the new password.')
            ->addRule(
                Form::Equal,
                'Passwords do not match.',
                $form['new_password'],
            );

        $form->addSubmit('submit', 'Set New Password')
            ->setHtmlAttribute('class', 'btn btn-warning');

        $form->onSuccess[] = [$this, 'passwordFormSucceeded'];

        return $form;
    }

    public function passwordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->userFacade->resetPassword($this->targetUser->id, $values->new_password);
            $this->flashMessage('Password has been reset successfully.', 'success');
            $this->redirect('edit', $this->targetUser->id);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to reset password: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  DELETE (soft)
    // ==================================================================

    public function actionDelete(int $id): void
    {
        $user = $this->requireActiveUser($id);

        if ($user->id === (int) $this->getUser()->getId()) {
            $this->flashMessage('You cannot delete your own account.', 'danger');
            $this->redirect('default');
        }

        $this->targetUser = $user;
    }

    public function renderDelete(int $id): void
    {
        $this->template->title      = 'Delete User';
        $this->template->targetUser = $this->targetUser;
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSubmit('delete', 'Yes, Delete This User')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'deleteFormSucceeded'];

        return $form;
    }

    public function deleteFormSucceeded(Form $form, \stdClass $values): void
    {
        // Belt-and-suspenders re-check.
        if ($this->targetUser->id === (int) $this->getUser()->getId()) {
            $this->flashMessage('You cannot delete your own account.', 'danger');
            $this->redirect('default');
        }

        try {
            $name = $this->targetUser->first_name . ' ' . $this->targetUser->last_name;
            $this->userFacade->softDelete($this->targetUser->id);
            $this->flashMessage("User \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete user: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  Internal helpers
    // ==================================================================

    /**
     * Loads a non-deleted user or terminates with 404.
     */
    private function requireActiveUser(int $id): ActiveRow
    {
        $user = $this->userRepository->findById($id);

        if ($user === null || $user->deleted_at !== null) {
            $this->error('User not found.', 404);
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return [
            'employee' => 'Employee',
            'support'  => 'Support',
            'admin'    => 'Admin',
            'guest'    => 'Guest',
        ];
    }
}
