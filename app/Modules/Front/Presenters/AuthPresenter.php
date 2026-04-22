<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\UserFacade;
use App\Model\Repository\UserRepository;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;

final class AuthPresenter extends BasePresenter
{
    public function __construct(
        private readonly UserFacade      $userFacade,
        private readonly UserRepository  $userRepository,
    ) {
        parent::__construct();
    }

    // ------------------------------------------------------------------
    //  Login
    // ------------------------------------------------------------------

    public function actionLogin(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect(':Front:Homepage:default');
        }
    }

    protected function createComponentLoginForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addEmail('email', 'Email address')
            ->setRequired('Please enter your email address.')
            ->setHtmlAttribute('placeholder', 'you@example.com')
            ->setHtmlAttribute('autocomplete', 'email');

        $form->addPassword('password', 'Password')
            ->setRequired('Please enter your password.')
            ->setHtmlAttribute('autocomplete', 'current-password');

        $form->addSubmit('login', 'Log In')
            ->setHtmlAttribute('class', 'btn btn-primary w-100');

        $form->onSuccess[] = [$this, 'loginFormSucceeded'];

        return $form;
    }

    public function loginFormSucceeded(Form $form, mixed $values): void
    {
        $data     = $form->getValues(true);
        $email    = RowType::string($data['email']);
        $password = RowType::string($data['password']);

        try {
            $this->getUser()->login($email, $password);
            $this->redirect(':Front:Homepage:default');
        } catch (AuthenticationException $e) {
            if ($e->getCode() === Authenticator::NOT_APPROVED) {
                $message = match ($e->getMessage()) {
                    'pending'  => 'Your account is awaiting admin approval.',
                    'rejected' => 'Your account has been rejected. Please contact support.',
                    default    => 'Your account is not active.',
                };
                $form->addError($message);
            } else {
                $form->addError('Invalid email or password.');
            }
        }
    }

    // ------------------------------------------------------------------
    //  Register
    // ------------------------------------------------------------------

    public function actionRegister(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect(':Front:Homepage:default');
        }
    }

    protected function createComponentRegisterForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('first_name', 'First name')
            ->setRequired('Please enter your first name.')
            ->setMaxLength(80);

        $form->addText('last_name', 'Last name')
            ->setRequired('Please enter your last name.')
            ->setMaxLength(80);

        $form->addEmail('email', 'Email address')
            ->setRequired('Please enter your email address.')
            ->setHtmlAttribute('autocomplete', 'email');

        $password = $form->addPassword('password', 'Password')
            ->setRequired('Please enter a password.')
            ->addRule(Form::MinLength, 'Password must be at least 8 characters long.', 8)
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addPassword('password_confirm', 'Confirm password')
            ->setRequired('Please confirm your password.')
            ->addRule(Form::Equal, 'Passwords do not match.', $password)
            ->setOmitted()
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addText('phone', 'Phone number')
            ->setMaxLength(30)
            ->setHtmlAttribute('placeholder', '+420 600 000 000');

        $form->addText('birth_date', 'Date of birth')
            ->setHtmlAttribute('type', 'date');

        $form->addText('street', 'Street and house number')
            ->setMaxLength(180);

        $form->addText('city', 'City')
            ->setMaxLength(100);

        $form->addSubmit('register', 'Create Account')
            ->setHtmlAttribute('class', 'btn btn-primary w-100');

        $form->onSuccess[] = [$this, 'registerFormSucceeded'];

        return $form;
    }

    public function registerFormSucceeded(Form $form, mixed $values): void
    {
        $data  = $form->getValues(true);
        $email = RowType::string($data['email']);

        if ($this->userRepository->emailExists($email)) {
            $emailField = $form->getComponent('email');
            if ($emailField instanceof \Nette\Forms\Controls\BaseControl) {
                $emailField->addError('This email address is already registered.');
            } else {
                $form->addError('This email address is already registered.');
            }
            return;
        }

        $this->userFacade->register([
            'first_name' => RowType::string($data['first_name']),
            'last_name'  => RowType::string($data['last_name']),
            'email'      => $email,
            'password'   => RowType::string($data['password']),
            'phone'      => is_string($data['phone'] ?? null) ? $data['phone'] : '',
            'birth_date' => is_string($data['birth_date'] ?? null) ? $data['birth_date'] : '',
            'street'     => is_string($data['street'] ?? null) ? $data['street'] : '',
            'city'       => is_string($data['city'] ?? null) ? $data['city'] : '',
        ]);

        $this->flashMessage(
            'Your account has been created and is pending admin approval.'
                . ' You will be notified by email once it has been reviewed.',
            'success',
        );
        $this->redirect('pending');
    }

    // ------------------------------------------------------------------
    //  Pending-approval info page
    // ------------------------------------------------------------------

    public function actionPending(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect(':Front:Homepage:default');
        }
    }

    // ------------------------------------------------------------------
    //  Logout
    // ------------------------------------------------------------------

    public function actionLogout(): void
    {
        $this->getUser()->logout(true);
        $this->redirect(':Front:Homepage:default');
    }
}
