<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\UserFacade;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Paginator;

final class UserPresenter extends BasePresenter
{
    private const ITEMS_PER_PAGE = 15;

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
        $roleRaw = $this->getParameter('role');
        $role = is_string($roleRaw) ? $roleRaw : '';

        $statusRaw = $this->getParameter('status');
        $status = is_string($statusRaw) ? $statusRaw : '';

        $searchRaw = $this->getParameter('search');
        $search = is_string($searchRaw) ? trim($searchRaw) : '';

        $filters = ['role' => $role, 'status' => $status];

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
                function (\Nette\Forms\IControl $input): bool {
                    $v = $input->getValue();
                    return !$this->userRepository->emailExistsExcept(is_string($v) ? $v : '');
                },
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

    public function createFormSucceeded(Form $form, mixed $values): void
    {
        try {
            $data = $form->getValues(true);
            $this->userFacade->createByAdmin([
                'first_name' => RowType::string($data['first_name']),
                'last_name'  => RowType::string($data['last_name']),
                'email'      => RowType::string($data['email']),
                'password'   => RowType::string($data['password']),
                'role'       => RowType::string($data['role']),
                'phone'      => is_string($data['phone'] ?? null) ? $data['phone'] : '',
                'birth_date' => is_string($data['birth_date'] ?? null) ? $data['birth_date'] : '',
                'street'     => is_string($data['street'] ?? null) ? $data['street'] : '',
                'city'       => is_string($data['city'] ?? null) ? $data['city'] : '',
            ]);
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
        $u = $this->targetUser ?? throw new \LogicException('Target user not loaded.');

        $this->template->title      = 'Edit User — ' . RowType::string($u->first_name) . ' ' . RowType::string($u->last_name);
        $this->template->targetUser = $u;
        $this->template->isSelf     = (RowType::int($u->id) === (int) $this->getUser()->getId());

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
                function (\Nette\Forms\IControl $input): bool {
                    $v = $input->getValue();
                    $excludeId = $this->targetUser !== null ? RowType::int($this->targetUser->id) : null;
                    return !$this->userRepository->emailExistsExcept(is_string($v) ? $v : '', $excludeId);
                },
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

    public function editFormSucceeded(Form $form, mixed $values): void
    {
        $user   = $this->targetUser ?? throw new \LogicException('Target user not loaded.');
        $userId = RowType::int($user->id);
        $isSelf = ($userId === (int) $this->getUser()->getId());

        $data = $form->getValues(true);
        $roleValue   = RowType::string($data['role']);
        $statusValue = RowType::string($data['status']);

        if ($isSelf) {
            if ($roleValue !== 'admin') {
                $form->addError('You cannot change your own role.');
                return;
            }
            if ($statusValue !== 'approved') {
                $form->addError('You cannot change your own account status.');
                return;
            }
        }

        try {
            $this->userFacade->update($userId, [
                'first_name' => RowType::string($data['first_name']),
                'last_name'  => RowType::string($data['last_name']),
                'email'      => RowType::string($data['email']),
                'role'       => $roleValue,
                'status'     => $statusValue,
                'phone'      => is_string($data['phone'] ?? null) ? $data['phone'] : '',
                'birth_date' => is_string($data['birth_date'] ?? null) ? $data['birth_date'] : '',
                'street'     => is_string($data['street'] ?? null) ? $data['street'] : '',
                'city'       => is_string($data['city'] ?? null) ? $data['city'] : '',
            ]);
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
        $user = $this->targetUser ?? throw new \LogicException('Target user not loaded.');
        $this->template->title      = 'Reset Password — ' . RowType::string($user->first_name) . ' ' . RowType::string($user->last_name);
        $this->template->targetUser = $user;
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

    public function passwordFormSucceeded(Form $form, mixed $values): void
    {
        $user = $this->targetUser ?? throw new \LogicException('Target user not loaded.');
        $userId = RowType::int($user->id);

        try {
            $data = $form->getValues(true);
            $this->userFacade->resetPassword($userId, RowType::string($data['new_password']));
            $this->flashMessage('Password has been reset successfully.', 'success');
            $this->redirect('edit', $userId);
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

        if (RowType::int($user->id) === (int) $this->getUser()->getId()) {
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

    public function deleteFormSucceeded(Form $form, mixed $values): void
    {
        $user = $this->targetUser ?? throw new \LogicException('Target user not loaded.');
        $userId = RowType::int($user->id);

        if ($userId === (int) $this->getUser()->getId()) {
            $this->flashMessage('You cannot delete your own account.', 'danger');
            $this->redirect('default');
        }

        try {
            $name = RowType::string($user->first_name) . ' ' . RowType::string($user->last_name);
            $this->userFacade->softDelete($userId);
            $this->flashMessage("User \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete user: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  Internal helpers
    // ==================================================================

    private function requireActiveUser(int $id): ActiveRow
    {
        $user = $this->userRepository->findById($id);

        if ($user === null || $user->deleted_at !== null) {
            $this->error('User not found.', 404);
        }

        return $user;
    }

    /** @return array<string, string> */
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
