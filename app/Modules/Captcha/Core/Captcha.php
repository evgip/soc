<?php

declare(strict_types=1);

namespace App\Modules\Captcha\Core;

use W3a\Core\Config;
use W3a\Core\Request;
use W3a\Core\Session;

class Captcha
{
    private Config $config;
    private Request $request;
    private Session $session;
    private ?string $driver = null;

    public function __construct(Config $config, Request $request, Session $session)
    {
        $this->config = $config;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * Получить драйвер из конфига
     */
    private function getDriver(): string
    {
        if ($this->driver === null) {
            $this->driver = $this->config->getString('captcha.config.driver', 'yandex');
        }
        return $this->driver;
    }

    /**
     * Проверить, включена ли капча
     */
    public function isEnabled(): bool
    {
        return $this->config->getBool('captcha.config.enabled', true);
    }

    /**
     * Нужно ли показывать капчу текущему пользователю?
     */
    public function isRequired(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Если пользователь авторизован и настроен пропуск
        if ($this->config->getBool('captcha.config.skip_for_authorized_users', true)) {
            $userId = $this->session->get('user_id');
            if (!empty($userId)) {
                $minKarma = $this->config->getInt('captcha.config.min_karma_to_skip', 50);
                $userKarma = $this->session->get('user_karma', 0);
                if ($userKarma >= $minKarma) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Получить HTML-код капчи
     */
    public function getHtml(): string
    {
        if (!$this->isRequired()) {
            return '';
        }

        return match ($this->getDriver()) {
            'yandex' => $this->getYandexCaptcha(),
            'google' => $this->getGoogleCaptcha(),
            'custom' => $this->getCustomCaptcha(),
            default => throw new \RuntimeException("Unknown captcha driver: " . $this->getDriver()),
        };
    }

    /**
     * Проверить ответ капчи
     */
    public function validate(?string $token = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        return match ($this->getDriver()) {
            'yandex' => $this->validateYandex($token),
            'google' => $this->validateGoogle($token),
            'custom' => $this->validateCustom($token),
            default => false,
        };
    }

    /**
     * Yandex SmartCaptcha HTML
     */
    private function getYandexCaptcha(): string
    {
        $siteKey = $this->config->getString('captcha.config.yandex.site_key', '');
        
        if (empty($siteKey)) {
            return '<!-- Yandex Captcha: site_key not configured -->';
        }

        return <<<HTML
<script src="https://captcha-api.yandex.ru/captcha.js" defer></script>
<div id="captcha-container" 
     class="smart-captcha" 
     data-sitekey="{$siteKey}">
</div>
HTML;
    }

    /**
     * Google reCAPTCHA HTML
     */
    private function getGoogleCaptcha(): string
    {
        $siteKey = $this->config->getString('captcha.config.google.site_key', '');
        
        if (empty($siteKey)) {
            return '<!-- Google Captcha: site_key not configured -->';
        }

        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js" defer></script>
<div class="g-recaptcha" data-sitekey="{$siteKey}"></div>
HTML;
    }

    /**
     * Кастомная капча HTML
     */
    private function getCustomCaptcha(): string
    {
        $num1 = random_int(1, 10);
        $num2 = random_int(1, 10);
        $this->session->set('captcha_answer', $num1 + $num2);

        return <<<HTML
<div class="custom-captcha">
    <label>Сколько будет {$num1} + {$num2}?</label>
    <input type="number" name="captcha_answer" required>
</div>
HTML;
    }

    /**
     * Валидация Yandex
     */
    private function validateYandex(?string $token): bool
    {
        $token = $token ?? $this->request->post('smart-token');
        
        if (empty($token)) {
            return false;
        }

        $secretKey = $this->config->getString('captcha.config.yandex.secret_key', '');
        $submitUrl = $this->config->getString(
            'captcha.config.yandex.submit_url', 
            'https://smartcaptcha.cloud.yandex.ru/validate'
        );
        
        if (empty($secretKey)) {
            return false;
        }

        $response = $this->sendHttpRequest($submitUrl, [
            'secret' => $secretKey,
            'token'  => $token,
            'ip'     => $this->request->getIp(),
        ]);

        if ($response === null) {
            return false;
        }

        $data = json_decode($response, true);
        
        return isset($data['status']) && $data['status'] === 'ok';
    }

    /**
     * Валидация Google
     */
    private function validateGoogle(?string $token): bool
    {
        $token = $token ?? $this->request->post('g-recaptcha-response');
        
        if (empty($token)) {
            return false;
        }

        $secretKey = $this->config->getString('captcha.config.google.secret_key', '');
        $submitUrl = $this->config->getString(
            'captcha.config.google.submit_url', 
            'https://www.google.com/recaptcha/api/siteverify'
        );
        
        if (empty($secretKey)) {
            return false;
        }

        $response = $this->sendHttpRequest($submitUrl, [
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => $this->request->getIp(),
        ]);

        if ($response === null) {
            return false;
        }

        $data = json_decode($response, true);
        
        return isset($data['success']) && $data['success'] === true;
    }

    /**
     * Валидация кастомной капчи
     */
    private function validateCustom(?string $token): bool
    {
        $answer = $token ?? $this->request->post('captcha_answer');
        
        if ($answer === null) {
            return false;
        }

        $correct = $this->session->get('captcha_answer');
        $this->session->remove('captcha_answer');

        if ($correct === null) {
            return false;
        }

        return (int)$answer === (int)$correct;
    }

    /**
     * Отправить HTTP POST запрос
     */
    private function sendHttpRequest(string $url, array $data): ?string
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("HTTP request failed: {$error} (HTTP {$httpCode})");
            return null;
        }

        return $response;
    }
}