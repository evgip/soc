<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\FriendLink;
use App\Modules\Stories\Models\Story;
use W3a\Core\Support\Logger;

class FriendLinkService
{
    public function __construct(
        private FriendLink $friendLinkModel,
        private Story $storyModel,
        private Logger $logger
    ) {}
    
    /**
     * Создать новую friend link для статьи
     */
    public function createLink(int $storyId, int $userId): string
    {
        // Проверка: автор ли это статьи?
        $story = $this->storyModel->find($storyId);
        
        if (!$story) {
            throw new \InvalidArgumentException('Статья не найдена');
        }
        
        if ((int)$story['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Нет прав для создания ссылки');
        }
        
        // Проверка: есть ли пейволл?
        if (empty($story['has_paywall'])) {
            throw new \InvalidArgumentException('Ссылка не нужна для открытых статей');
        }
        
        $linkId = $this->friendLinkModel->generate($storyId, $userId);
        $link = $this->friendLinkModel->find($linkId);
        
        if (!$link) {
            throw new \RuntimeException('Не удалось создать ссылку');
        }
        
        return $link['token'];
    }
    
    /**
     * Проверить friend link при доступе к статье
     * Возвращает true если доступ разрешен
     */
    public function checkAccess(string $token, int $storyId): bool
    {
        if (empty($token)) {
            return false;
        }
        
        $link = $this->friendLinkModel->findByToken($token);
        
        if (!$link) {
            return false;
        }
        
        if ((int)$link['story_id'] !== $storyId) {
            return false;
        }
        
        if (!$this->friendLinkModel->isValid($link)) {
            return false;
        }
        
        // Инкрементируем счетчик использований
        $this->friendLinkModel->incrementUses((int)$link['id']);
        
        return true;
    }
    
    /**
     * Получить все ссылки для статьи (только для автора)
     */
    public function getLinksForStory(int $storyId, int $userId): array
    {
        // Проверка: автор ли это статьи?
        $story = $this->storyModel->find($storyId);
        
        if (!$story || (int)$story['user_id'] !== $userId) {
            return [];
        }
        
        return $this->friendLinkModel->getByStory($storyId, $userId);
    }
    
    /**
     * Деактивировать ссылку
     */
    public function deactivateLink(int $linkId, int $userId): bool
    {
        // Получаем ссылку и проверяем права
        $link = $this->friendLinkModel->find($linkId);
        
        if (!$link || (int)$link['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Нет прав для удаления ссылки');
        }
        
        return $this->friendLinkModel->deactivate($linkId, $userId);
    }
}