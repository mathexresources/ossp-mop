<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
use App\Model\Database\RowType;
use App\Model\Facade\UserFacade;
use App\Model\Repository\UserRepository;
use Nette\Application\UI\Form;

final class ProfilePresenter extends SecuredPresenter
{
    protected ?string $requiredRole = null;

    private UserFacade      $userFacade;
    private UserRepository  $userRepository;

    public function injectUserFacade(UserFacade $userFacade): void
    {
        $this->userFacade = $userFacade;
    }

    public function injectUserRepository(UserRepository $userRepository): void
    {
        $this->userRepository = $userRepository;
    }

    // ==================================================================
    //  Edit / view profile
    // ==================================================================

    public function renderEdit(): void
    {
        $userId = (int) $this->getUser()->getId();
        $user   = $this->userRepository->findById($userId);

        if ($user === null) {
            $this->error('User not found.', 404);
        }

        $this->template->title    = 'My Profile';
        $this->template->profile  = $user;
        $this->template->initials = mb_strtoupper(
            mb_substr(RowType::string($user->first_name), 0, 1) .
            mb_substr(RowType::string($user->last_name), 0, 1),
        );

        $this['profileForm']->setDefaults([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'phone'      => $user->phone,
            'birth_date' => $user->birth_date,
            'street'     => $user->street,
            'city'       => $user->city,
        ]);
    }

    protected function createComponentProfileForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('first_name', 'First Name')
            ->setRequired('First name is required.')
            ->setMaxLength(80);

        $form->addText('last_name', 'Last Name')
            ->setRequired('Last name is required.')
            ->setMaxLength(80);

        $form->addText('phone', 'Phone Number')
            ->setMaxLength(30)
            ->setHtmlAttribute('placeholder', '+420 600 000 000');

        $form->addText('birth_date', 'Date of Birth')
            ->setHtmlAttribute('type', 'date');

        $form->addText('street', 'Street and House Number')
            ->setMaxLength(180);

        $form->addText('city', 'City')
            ->setMaxLength(100);

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'profileFormSucceeded'];

        return $form;
    }

    public function profileFormSucceeded(Form $form, mixed $values): void
    {
        $userId = (int) $this->getUser()->getId();
        $data   = $form->getValues(true);

        try {
            $this->userFacade->updateProfile($userId, [
                'first_name' => RowType::string($data['first_name']),
                'last_name'  => RowType::string($data['last_name']),
                'phone'      => is_string($data['phone'] ?? null) ? $data['phone'] : '',
                'birth_date' => is_string($data['birth_date'] ?? null) ? $data['birth_date'] : '',
                'street'     => is_string($data['street'] ?? null) ? $data['street'] : '',
                'city'       => is_string($data['city'] ?? null) ? $data['city'] : '',
            ]);
            $this->flashMessage('Profile updated successfully.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to update profile: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('edit');
    }

    // ==================================================================
    //  Change password
    // ==================================================================

    protected function createComponentChangePasswordForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addPassword('current_password', 'Current Password')
            ->setRequired('Please enter your current password.')
            ->setHtmlAttribute('autocomplete', 'current-password');

        $newPassword = $form->addPassword('new_password', 'New Password')
            ->setRequired('Please enter a new password.')
            ->addRule(Form::MinLength, 'Password must be at least 8 characters.', 8)
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addPassword('new_password_confirm', 'Confirm New Password')
            ->setRequired('Please confirm your new password.')
            ->addRule(Form::Equal, 'Passwords do not match.', $newPassword)
            ->setOmitted()
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addSubmit('submit', 'Change Password')
            ->setHtmlAttribute('class', 'btn btn-warning');

        $form->onSuccess[] = [$this, 'changePasswordFormSucceeded'];

        return $form;
    }

    public function changePasswordFormSucceeded(Form $form, mixed $values): void
    {
        $userId  = (int) $this->getUser()->getId();
        $data    = $form->getValues(true);
        $current = RowType::string($data['current_password']);
        $new     = RowType::string($data['new_password']);

        try {
            $this->userFacade->changeOwnPassword($userId, $current, $new);
            $this->flashMessage('Password changed successfully.', 'success');
        } catch (\RuntimeException $e) {
            $pwField = $form->getComponent('current_password');
            if ($pwField instanceof \Nette\Forms\Controls\BaseControl) {
                $pwField->addError($e->getMessage());
            } else {
                $form->addError($e->getMessage());
            }
            return;
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to change password: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('edit');
    }
}
