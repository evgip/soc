<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;
use App\Modules\Users\Exceptions\UserValidationException;
use App\Modules\Users\Exceptions\UserNotFoundException;

/**
 * Сервис для работы с данными пользователей.
 */
class UserService
{
    private User $userModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function getUserWithProfile(int $userId): ?array
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            return null;
        }

        $profile = $this->userModel->getProfile($userId);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        $settings = $this->userModel->getSettings($userId);
        $user['notify_on_reply'] = $settings['notify_on_reply'] ?? 1;
        $user['notify_on_story_comment'] = $settings['notify_on_story_comment'] ?? 1;
        $user['notify_on_mention'] = $settings['notify_on_mention'] ?? 1;
        $user['notify_on_message'] = $settings['notify_on_message'] ?? 1;
        $user['email_notifications'] = $settings['email_notifications'] ?? 0;

        return $user;
    }

    /**
     * Обновляет email пользователя.
     *
     * @throws UserValidationException Если email уже занят
     */
    public function updateEmail(int $userId, string $newEmail): bool
    {
        $existingUser = $this->userModel->findBy('email', $newEmail);
        if ($existingUser && (int)$existingUser['id'] !== $userId) {
            throw new UserValidationException('Этот Email уже занят.');
        }

        return $this->userModel->update($userId, ['email' => $newEmail]);
    }

    public function updateProfile(int $userId, array $data): bool
    {
        return $this->userModel->updateProfile($userId, $data);
    }

	public function updateSettings(int $userId, array $data): bool
	{
		return $this->userModel->updateSettings($userId, [
			'notify_on_reply'             => $data['notify_on_reply'] ?? 0,
			'notify_on_story_comment'     => $data['notify_on_story_comment'] ?? 0,
			'notify_on_mention'           => $data['notify_on_mention'] ?? 0,
			'notify_on_message'           => $data['notify_on_message'] ?? 0,
			'notify_on_collection_update' => $data['notify_on_collection_update'] ?? 1,
			'email_notifications'         => $data['email_notifications'] ?? 0,
		]);
	}

    /**
     * Меняет пароль пользователя.
     *
     * @throws UserNotFoundException Если пользователь не найден
     * @throws UserValidationException Если текущий пароль неверен
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            throw new UserNotFoundException('Пользователь не найден.');
        }

        if (!password_verify($currentPassword, $user['password'])) {
            throw new UserValidationException('Текущий пароль введён неверно.');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->userModel->update($userId, ['password' => $hashedPassword]);
    }

    public function getUserSettings(int $userId): array
    {
        $settings = $this->userModel->getSettings($userId);
        
        if (!$settings) {
			return [
				'notify_on_reply'             => 1,
				'notify_on_story_comment'     => 1,
				'notify_on_mention'           => 1,
				'notify_on_message'           => 1,
				'notify_on_collection_update' => 1,
				'email_notifications'         => 0,
			];
        }
        
        return $settings;
    }

    public function getAllUsers(): array
    {
        return $this->userModel->getAllUsersWithBanStatus(withTrashed: true);
    }
    
    public function getUserOpenGraphData(string $username): array
    {
        $user = $this->userModel->findBy('username', $username);
        if (!$user) {
            return [];
        }
        
        return [
            'title' => $user['username'],
            'description' => $user['bio'] ?? 'Пользователь ' . $user['username'],
            'image' => !empty($user['avatar']) 
                ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/uploads/avatars/' . $user['avatar']
                : null,
        ];
    }
}