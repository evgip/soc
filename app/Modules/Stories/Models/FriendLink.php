<?php
declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Database\Model;

class FriendLink extends Model
{
    protected string $table = 'friend_links';
    
    protected array $fillable = [
        'story_id', 'user_id', 'token', 'uses_count', 
        'max_uses', 'is_active', 'expires_at'
    ];
    
    /**
     * Создать новую friend link
     */
    public function generate(int $storyId, int $userId): int
    {
        $token = bin2hex(random_bytes(32)); // 64 символа
        
        return $this->create([
            'story_id' => $storyId,
            'user_id' => $userId,
            'token' => $token,
            'uses_count' => 0,
            'is_active' => 1,
        ]);
    }
    
    /**
     * Найти активную ссылку по токену
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE token = ? AND is_active = 1 
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Проверить валидность ссылки
     */
    public function isValid(array $link): bool
    {
        // Проверка лимита использований
        if ($link['max_uses'] !== null && $link['uses_count'] >= $link['max_uses']) {
            return false;
        }
        
        // Проверка срока действия
        if ($link['expires_at'] !== null && strtotime($link['expires_at']) < time()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Инкрементировать счетчик использований
     */
    public function incrementUses(int $linkId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET uses_count = uses_count + 1 WHERE id = ?",
            [$linkId]
        );
    }
    
    /**
     * Получить все ссылки автора для статьи
     */
    public function getByStory(int $storyId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE story_id = ? AND user_id = ? AND is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([$storyId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Деактивировать ссылку
     */
    public function deactivate(int $linkId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET is_active = 0 WHERE id = ? AND user_id = ?",
            [$linkId, $userId]
        ) > 0;
    }
}