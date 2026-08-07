<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Services;

use W3a\Core\Support\Logger;
use W3a\Core\Events\EventDispatcher;
use App\Modules\SocialAuth\Models\SocialAccount;
use App\Modules\Users\Models\User;
use App\Modules\SocialAuth\Events\SocialUserCreated;

class SocialAuthService
{
    private Logger $logger;
    private SocialAccount $socialAccountModel;
    private User $userModel;
    private EventDispatcher $eventDispatcher;

    public function __construct(
        Logger $logger,
        SocialAccount $socialAccountModel,
        User $userModel,
        EventDispatcher $eventDispatcher
    ) {
        $this->logger = $logger;
        $this->socialAccountModel = $socialAccountModel;
        $this->userModel = $userModel;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Авторизовать пользователя через социальный провайдер.
     * 
     * Логика:
     * 1. Если есть привязка — логиним
     * 2. Если есть пользователь с таким email — привязываем и логиним
     * 3. Иначе — создаём нового пользователя и привязываем
     * 
     * @return array{user_id: int, is_new: bool}
     */
    public function authenticate(string $provider, array $providerUser): array
    {
        $providerUserId = (string)($providerUser['id'] ?? '');

        if (empty($providerUserId)) {
            throw new \InvalidArgumentException('Provider user ID is required');
        }

        // 1. Есть ли уже привязка?
        $socialAccount = $this->socialAccountModel->findByProviderUser($provider, $providerUserId);

        if ($socialAccount) {
            return ['user_id' => (int)$socialAccount['user_id'], 'is_new' => false];
        }

        // 2. Есть ли пользователь с таким email?
        $email = $providerUser['email'] ?? null;
        $existingUser = null;

        if (!empty($email)) {
            $existingUser = $this->userModel->findByEmail($email);
        }

        if ($existingUser) {
            $this->socialAccountModel->attach(
                (int)$existingUser['id'],
                $provider,
                $providerUserId,
                ['profile' => $providerUser]
            );

            return ['user_id' => (int)$existingUser['id'], 'is_new' => false];
        }

        // 3. Создаём нового пользователя
        $userId = $this->createUser($provider, $providerUser);

        $this->socialAccountModel->attach($userId, $provider, $providerUserId, [
            'profile' => $providerUser,
        ]);

        $this->eventDispatcher->dispatch(new SocialUserCreated($userId, $provider));

        return ['user_id' => $userId, 'is_new' => true];
    }

    private function createUser(string $provider, array $data): int
    {
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? $data['username'] ?? '';
        $providerUserId = (string)$data['id'];

        $username = $this->generateUsername($name, $provider);

        $userId = $this->userModel->createOAuthUser(
            $username,
            $email,
            $provider,
            $providerUserId
        );

        $this->logger->info("New user created via {$provider}: {$username} (ID: {$userId})");

        return $userId;
    }

    private function generateUsername(string $base, string $provider): string
    {
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $base);
        
        if (strlen($username) < 3) {
            $username = $provider . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        // Ограничиваем длиной 90 символов (с запасом под суффикс)
        $username = mb_substr($username, 0, 90);

        $original = $username;
        $attempts = 0;
        
        while ($this->userModel->findByName($username) && $attempts < 10) {
            $username = $original . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
            $attempts++;
        }

        return $username;
    }
}