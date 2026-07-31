<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use W3a\Core\Auth\PasswordResetService as BasePasswordResetService;
use W3a\Core\Support\Lang;
use W3a\Core\Auth\Models\User;
use App\Modules\Mail\Core\Mailer;

/**
 * Расширение PasswordResetService для специфики приложения.
 * Переопределяет только отправку писем через Mailer приложения.
 */
class AppPasswordResetService extends BasePasswordResetService
{
    private Mailer $mailer;

    public function __construct(User $userModel, \W3a\Core\Auth\Models\PasswordResetToken $tokenModel, Mailer $mailer)
    {
        parent::__construct($userModel, $tokenModel, $mailer);
        $this->mailer = $mailer;
    }

    /**
     * Переопределяем: формирование URL с учётом app.url из конфига.
     */
    protected function getResetUrl(string $token): string
    {
        $baseUrl = rtrim(config('app.url') ?? '', '/');
        
        if (empty($baseUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        
        return $baseUrl . '/password/reset/' . $token;
    }

    /**
     * Переопределяем: отправка письма через Mailer приложения.
     */
    protected function sendResetEmail(string $email, string $username, string $resetUrl): void
    {
        $siteName = config('app.name') ?? config('app.url');

        $subject = sprintf(
            Lang::get('email_recovery_subject'),
            htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8')
        );
        
        $body = sprintf(
            Lang::get('email_recovery_body'),
            htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8')
        );

        $this->mailer->send($email, $subject, $body);
    }
}