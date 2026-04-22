<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\UserFacade;
use Nette\Application\UI\Form;

final class UserApprovalPresenter extends BasePresenter
{
    private UserFacade $userFacade;

    public function injectUserFacade(UserFacade $userFacade): void
    {
        $this->userFacade = $userFacade;
    }

    // ------------------------------------------------------------------
    //  Pending-users list
    // ------------------------------------------------------------------

    public function renderDefault(): void
    {
        $this->template->title        = 'Pending User Approvals';
        $this->template->pendingUsers = $this->userRepository->findByStatus('pending');
    }

    // ------------------------------------------------------------------
    //  Approve — confirmation page
    // ------------------------------------------------------------------

    public function renderApprove(int $id): void
    {
        $user = $this->userRepository->findById($id);
        if ($user === null || RowType::string($user->status) !== 'pending') {
            $this->flashMessage('User not found or no longer pending.', 'warning');
            $this->redirect('default');
        }

        $this->template->title       = 'Approve User';
        $this->template->pendingUser = $user;
        $this['approveForm']->setDefaults(['userId' => $id]);
    }

    protected function createComponentApproveForm(): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->addHidden('userId');
        $form->addSubmit('approve', 'Approve User')
            ->setHtmlAttribute('class', 'btn btn-success');
        $form->onSuccess[] = [$this, 'approveFormSucceeded'];

        return $form;
    }

    public function approveFormSucceeded(Form $form, mixed $values): void
    {
        $data      = $form->getValues(true);
        $userIdRaw = $data['userId'] ?? null;
        $userId    = is_numeric($userIdRaw) ? (int) $userIdRaw : 0;
        $this->userFacade->approve($userId);
        $this->flashMessage('User account approved.', 'success');
        $this->redirect('default');
    }

    // ------------------------------------------------------------------
    //  Reject — form with optional reason
    // ------------------------------------------------------------------

    public function renderReject(int $id): void
    {
        $user = $this->userRepository->findById($id);
        if ($user === null || RowType::string($user->status) !== 'pending') {
            $this->flashMessage('User not found or no longer pending.', 'warning');
            $this->redirect('default');
        }

        $this->template->title       = 'Reject User';
        $this->template->pendingUser = $user;
        $this['rejectForm']->setDefaults(['userId' => $id]);
    }

    protected function createComponentRejectForm(): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->addHidden('userId');

        $form->addTextArea('reason', 'Reason for rejection (optional)')
            ->setHtmlAttribute('rows', 4)
            ->setHtmlAttribute('placeholder', 'Explain why the registration is being rejected…')
            ->setMaxLength(1000);

        $form->addSubmit('reject', 'Reject User')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'rejectFormSucceeded'];

        return $form;
    }

    public function rejectFormSucceeded(Form $form, mixed $values): void
    {
        $data      = $form->getValues(true);
        $userIdRaw = $data['userId'] ?? null;
        $userId    = is_numeric($userIdRaw) ? (int) $userIdRaw : 0;
        $reason = trim(is_string($data['reason'] ?? null) ? $data['reason'] : '');
        $this->userFacade->reject($userId, $reason !== '' ? $reason : null);
        $this->flashMessage('User account rejected.', 'warning');
        $this->redirect('default');
    }
}
