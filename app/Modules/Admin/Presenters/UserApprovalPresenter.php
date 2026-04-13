<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Facade\UserFacade;
use Nette\Application\UI\Form;

/**
 * Handles the dedicated pending-user approval flow.
 *
 * UserRepository is provided by Admin\BasePresenter via
 * injectUserRepository().  Only UserFacade needs an additional inject.
 */
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
        if ($user === null || $user->status !== 'pending') {
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

    public function approveFormSucceeded(Form $form, \stdClass $values): void
    {
        $this->userFacade->approve((int) $values->userId);
        $this->flashMessage('User account approved.', 'success');
        $this->redirect('default');
    }

    // ------------------------------------------------------------------
    //  Reject — form with optional reason
    // ------------------------------------------------------------------

    public function renderReject(int $id): void
    {
        $user = $this->userRepository->findById($id);
        if ($user === null || $user->status !== 'pending') {
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

    public function rejectFormSucceeded(Form $form, \stdClass $values): void
    {
        $reason = trim((string) ($values->reason ?? ''));
        $this->userFacade->reject((int) $values->userId, $reason !== '' ? $reason : null);
        $this->flashMessage('User account rejected.', 'warning');
        $this->redirect('default');
    }
}
