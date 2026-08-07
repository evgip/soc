<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Services;

use W3a\Core\Support\Logger;

class VKOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private Logger $logger;

    private const AUTH_URL = 'https://oauth.vk.com/authorize';
    private const TOKEN_URL = 'https://oauth.vk.com/access_token';
    private const USER_INFO_URL = 'https://api.vk.com/method/users.get';
    private const API_VERSION = '5.131';

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->clientId = (string)config('social_auth.vk.client_id', '');
        $this->clientSecret = (string)config('social_auth.vk.client_secret', '');
        $this->redirectUri = config('config.app.url') . '/auth/vk/callback';
    }

    /**
     * URL для редиректа на авторизацию VK.
     */
    public function getAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'email',
            'state' => $state,
            'v' => self::API_VERSION,
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * Обменять код на токен.
     * ВАЖНО: email приходит ИМЕННО ЗДЕСЬ, один раз!
     */
    public function exchangeCode(string $code): ?array
    {
        $response = $this->httpGet(self::TOKEN_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]));

        if (isset($response['error'])) {
            $this->logger->error('VK OAuth token error: ' . ($response['error_description'] ?? 'Unknown'));
            return null;
        }

        // Возвращаем все нужные поля, включая email (если пришёл)
        return [
            'access_token' => $response['access_token'] ?? null,
            'user_id' => $response['user_id'] ?? null,
            'email' => $response['email'] ?? null,  // ← ВАЖНО: сохранить!
            'expires_in' => $response['expires_in'] ?? null,
        ];
    }

    /**
     * Получить информацию о пользователе.
     */
    public function getUserInfo(string $accessToken, int $userId): ?array
    {
        $params = [
            'user_ids' => $userId,
            'fields' => 'photo_200,screen_name,verified',
            'access_token' => $accessToken,
            'v' => self::API_VERSION,
        ];

        $response = $this->httpGet(self::USER_INFO_URL . '?' . http_build_query($params));

        if (isset($response['error'])) {
            $this->logger->error('VK OAuth user info error: ' . ($response['error']['error_msg'] ?? 'Unknown'));
            return null;
        }

        return $response['response'][0] ?? null;
    }

    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('VK GET error: ' . $error);
            return [];
        }

        return json_decode((string)$response, true) ?? [];
    }
}