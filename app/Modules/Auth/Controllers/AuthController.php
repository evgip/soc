<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use W3a\Core\Http\Session;
use W3a\Core\Auth\AuthService;
use W3a\Core\Auth\PasswordResetService;
use W3a\Core\Auth\Exceptions\AuthBlockedException;
use W3a\Core\Auth\Exceptions\InvalidCredentialsException;
use W3a\Core\Auth\Exceptions\AccountNotActiveException;
use W3a\Core\Auth\Exceptions\RegistrationFailedException;
use W3a\Core\Auth\Exceptions\InvalidTokenException;

use App\BaseController;

/**
 * Контроллер аутентификации.
 * 
 * Отвечает за обработку HTTP-запросов, связанных с входом, регистрацией и восстановлением пароля.
 * Перехватывает исключения от AuthService и преобразует их в flash-сообщения.
 */
class AuthController extends BaseController
{
    public function showLoginForm(): void
    {
        $this->render('login', [
            'title' => 'Авторизация',
            'request' => $this->request
        ]);
    }

    public function login(): void
    {
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');
        $remember = (bool) $this->request->getParams('remember');

        $user = null;

        try {
            $user = $this->service(AuthService::class)->authenticate($email, $password);
            $this->service(AuthService::class)->createSession($user, $remember);
            
        } catch (AuthBlockedException | InvalidCredentialsException | AccountNotActiveException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack('/login');
            return;
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.login');
            $this->session()->flash('error', 'Произошла ошибка при входе в систему.');
            $this->redirectBack('/login');
            return;
        }

        $this->session()->flash('success', 'Добро пожаловать!');
        $this->redirect('/');
    }
	

    public function showRegisterForm(): void
    {
        if (config('invitations.config.invitations_enabled')) {
            $this->redirect(route('home'));
            return;
        }

        $old = $this->session()->get('old_input', []);
        $this->session()->delete('old_input');

        $this->render('register', [
            'title' => 'Регистрация нового пользователя',
            'request' => $this->request,
            'old' => $old
        ]);
    }

    public function register(): void
    {
        if (!captcha_validate($this->request->post('smart-token'))) {
            $this->session()->flash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirectBack('/register');
            return;
        }

        $username = trim($this->request->getParams('username'));
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');

        $validator = $this->container->get(\W3a\Core\Support\Validator::class);
        $validator->validate([
            'username' => $username, 
            'email' => $email, 
            'password' => $password,
        ], [
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$validator->isValid()) {
            $errors = $validator->getErrors();
            $errorMessages = array_merge(...array_values($errors));

            $this->session()->flash('error', implode('<br>', $errorMessages));
            $this->session()->set('old_input', ['username' => $username, 'email' => $email]);
            $this->redirectBack('/register');
            return;
        }

        try {
            $this->service(AuthService::class)->register($username, $email, $password);
        } catch (RegistrationFailedException $e) {
            $this->session()->set('old_input', ['username' => $username, 'email' => $email]);
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack('/register');
			return;
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.register');
            $this->session()->set('old_input', ['username' => $username, 'email' => $email]);
            $this->session()->flash('error', 'Произошла ошибка при регистрации.');
            $this->redirectBack('/register');
			return;
        }
		
        $this->session()->flash('success', 'Регистрация успешна! Проверьте почту для активации.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $this->service(AuthService::class)->logout();
        $this->redirect('/');
    }

    public function activateAccount(string $token): void
    {
        try {
            $this->service(AuthService::class)->activateAccount($token);
            
        } catch (InvalidTokenException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirect('/register');
			return;
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.activate');
            $this->session()->flash('error', 'Произошла ошибка при активации аккаунта.');
            $this->redirect('/register');
			return;
        }
		
		$this->session()->flash('success', 'Аккаунт успешно активирован! Теперь вы можете войти.');
		$this->redirect('/login');
    }

    public function showRequestResetForm(): void
    {
        $this->render('password/reset_request', [
            'title' => 'Восстановление пароля'
        ]);
    }

    public function sendResetLink(): void
    {
        if (captcha_is_required() && !captcha_validate($this->request->post('smart-token'))) {
            $this->session()->flash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirect(route('password.request'));
            return;
        }

        $email = filter_var($this->request->post('email', ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $this->session()->flash('error', 'Неверный email адрес.');
            $this->redirect(route('password.request'));
            return;
        }

        $this->getPasswordResetService()->sendResetLink($email);

        $this->session()->flash('success', 'Если email найден в системе, инструкция по восстановлению отправлена на почту.');
        $this->redirect(route('password.request'));
    }

    public function showResetPasswordForm(string $token): void
    {
        $user = $this->getPasswordResetService()->validateToken($token);

        if (!$user) {
            $this->session()->flash('error', 'Ссылка недействительна или истекла.');
            $this->redirect(route('password.request'));
            return;
        }

        $this->render('password/reset_form', [
            'title' => 'Установить новый пароль',
            'token' => $token
        ]);
    }

    public function executePasswordReset(): void
    {
        $token = $this->request->getParams('token');
        $password = $this->request->getParams('password');
        $passwordConfirm = $this->request->getParams('password_confirm');

        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            $this->session()->flash('error', 'Заполните все поля.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        if (strlen($password) < 6) {
            $this->session()->flash('error', 'Пароль должен быть не менее 6 символов.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->session()->flash('error', 'Пароли не совпадают.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        $success = $this->getPasswordResetService()->resetPassword($token, $password);

        if ($success) {
            $this->session()->flash('success', 'Пароль успешно изменён. Теперь вы можете войти.');
            $this->redirect(route('auth.login'));
        } else {
            $this->session()->flash('error', 'Ошибка при смене пароля. Попробуйте запросить новую ссылку.');
            $this->redirect(route('password.request'));
        }
    }

    private function getPasswordResetService(): PasswordResetService
    {
        return $this->service(PasswordResetService::class);
    }

    /**
     * Хелпер для получения экземпляра Session.
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }
}