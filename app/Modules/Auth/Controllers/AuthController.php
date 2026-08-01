<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\MessageBag;

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
 * Перехватывает исключения от AuthService и преобразует их в flash-сообщения через MessageBag.
 */
class AuthController extends BaseController
{
    public function showLoginForm(): ViewResponse
    {
        return $this->render('login', [
            'title' => 'Авторизация',
            'request' => $this->request
        ]);
    }

    public function login(): RedirectResponse
    {
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');
        $remember = (bool) $this->request->getParams('remember');

        $validation = $this->validateRequest([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.email' => 'Введите корректный email-адрес',
        ]);

        if ($validation !== true) {
            return $validation; // Возвращаем RedirectResponse в Router
        }

        try {
            $user = $this->service(AuthService::class)->authenticate($email, $password);
            $this->service(AuthService::class)->createSession($user, $remember);
        } catch (AuthBlockedException | InvalidCredentialsException | AccountNotActiveException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack('/login');
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.login');
            MessageBag::flashMessage('error', 'Произошла ошибка при входе в систему.');
            return $this->redirectBack('/login');
        }

        MessageBag::flashMessage('success', 'Добро пожаловать!');
        return $this->redirect('/');
    }

    public function showRegisterForm(): ViewResponse
    {
        if (config('invitations.config.invitations_enabled')) {
            return $this->redirect(route('home'));
        }

        return $this->render('register', [
            'title' => 'Регистрация нового пользователя',
            'request' => $this->request,
        ]);
    }

    public function register(): RedirectResponse
    {
        if (!captcha_validate($this->request->post('smart-token'))) {
            MessageBag::flashMessage('error', 'Пожалуйста, подтвердите, что вы не робот.');
            return $this->redirectBack('/register');
        }

        $username = trim($this->request->getParams('username'));
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');

        $validation = $this->validateRequest([
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'username.regex' => 'Имя пользователя может содержать только латинские буквы, цифры и подчеркивание',
            'email.email' => 'Введите корректный email-адрес',
        ]);

        if ($validation !== true) {
            return $validation;
        }

        try {
            $this->service(AuthService::class)->register($username, $email, $password);
        } catch (RegistrationFailedException $e) {
            MessageBag::flashErrors([], ['username' => $username, 'email' => $email]);
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack('/register');
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.register');
            MessageBag::flashErrors([], ['username' => $username, 'email' => $email]);
            MessageBag::flashMessage('error', 'Произошла ошибка при регистрации.');
            return $this->redirectBack('/register');
        }

        MessageBag::flashMessage('success', 'Регистрация успешна! Проверьте почту для активации.');
        return $this->redirect('/login');
    }

    public function logout(): RedirectResponse
    {
        $this->service(AuthService::class)->logout();
        return $this->redirect('/');
    }

    public function activateAccount(string $token): RedirectResponse
    {
        try {
            $this->service(AuthService::class)->activateAccount($token);
            MessageBag::flashMessage('success', 'Аккаунт успешно активирован! Теперь вы можете войти.');
            return $this->redirect('/login');
        } catch (InvalidTokenException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect('/register');
        } catch (\Throwable $e) {
            $this->logError($e, 'Auth.activate');
            MessageBag::flashMessage('error', 'Произошла ошибка при активации аккаунта.');
            return $this->redirect('/register');
        }
    }

    public function showRequestResetForm(): ViewResponse
    {
        return $this->render('password/reset_request', [
            'title' => 'Восстановление пароля'
        ]);
    }

    public function sendResetLink(): RedirectResponse
    {
        if (captcha_is_required() && !captcha_validate($this->request->post('smart-token'))) {
            MessageBag::flashMessage('error', 'Пожалуйста, подтвердите, что вы не робот.');
            return $this->redirect(route('password.request'));
        }

        $email = filter_var($this->request->post('email', ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            MessageBag::flashMessage('error', 'Неверный email адрес.');
            return $this->redirect(route('password.request'));
        }

        $this->getPasswordResetService()->sendResetLink($email);

        MessageBag::flashMessage('success', 'Если email найден в системе, инструкция по восстановлению отправлена на почту.');
        return $this->redirect(route('password.request'));
    }

    public function showResetPasswordForm(string $token): Response
    {
        $user = $this->getPasswordResetService()->validateToken($token);

        if (!$user) {
            MessageBag::flashMessage('error', 'Ссылка недействительна или истекла.');
            return $this->redirect(route('password.request'));
        }

        return $this->render('password/reset_form', [
            'title' => 'Установить новый пароль',
            'token' => $token
        ]);
    }

    public function executePasswordReset(): RedirectResponse
    {
        $token = $this->request->getParams('token');
        $password = $this->request->getParams('password');
        $passwordConfirm = $this->request->getParams('password_confirm');

        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            MessageBag::flashMessage('error', 'Заполните все поля.');
            return $this->redirect(route('password.reset', ['token' => $token]));
        }

        if (strlen($password) < 6) {
            MessageBag::flashMessage('error', 'Пароль должен быть не менее 6 символов.');
            return $this->redirect(route('password.reset', ['token' => $token]));
        }

        if ($password !== $passwordConfirm) {
            MessageBag::flashMessage('error', 'Пароли не совпадают.');
            return $this->redirect(route('password.reset', ['token' => $token]));
        }

        $success = $this->getPasswordResetService()->resetPassword($token, $password);

        if ($success) {
            MessageBag::flashMessage('success', 'Пароль успешно изменён. Теперь вы можете войти.');
            return $this->redirect(route('auth.login'));
        } else {
            MessageBag::flashMessage('error', 'Ошибка при смене пароля. Попробуйте запросить новую ссылку.');
            return $this->redirect(route('password.request'));
        }
    }

    private function getPasswordResetService(): PasswordResetService
    {
        return $this->service(PasswordResetService::class);
    }
    
}