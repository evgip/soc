<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Services;

use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Models\ContentLog;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Comments\Models\Comment as StoryComment;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Services\TagValidator;
use App\Modules\Users\Models\User;
use App\Modules\Moderations\Models\Moderation;
use W3a\Core\Container;
use W3a\Core\Logger;
use W3a\Core\IpResolver;
use W3a\Core\Events\EventDispatcher;
use App\Modules\Suggestions\Events\ContentUpdated;
use App\Modules\Auth\Services\Auth;
use Exception;

/**
 * Сервис для работы с предложениями изменений контента.
 * 
 * Вся бизнес-логика здесь, SQL-запросы вынесены в модели.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости теперь обязательны и внедряются через DI-контейнер.
 */
class SuggestionService
{
    const QUORUM_SIZE = 3;
    const MAX_USER_SUGGESTIONS = 2;

    private Suggestion $suggestionModel;
    private ContentLog $contentLogModel;
    private Story $storyModel;
    private StoryComment $commentModel;
    private Tag $tagModel;
    private User $userModel;
    private Moderation $moderationModel;
    private EventDispatcher $eventDispatcher;
    private TagValidator $tagValidator;
    private StoryValidator $storyValidator;
    private Logger $logger;
    private IpResolver $ipResolver;

    /**
     * Конструктор с инъекцией всех зависимостей.
     * 
     * ✅ ИЗМЕНЕНО: Все зависимости обязательны.
     */
    public function __construct(
        Suggestion $suggestionModel,
        ContentLog $contentLogModel,
        Story $storyModel,
        StoryComment $commentModel,
        Tag $tagModel,
        User $userModel,
        Moderation $moderationModel,
        EventDispatcher $eventDispatcher,
        TagValidator $tagValidator,
        StoryValidator $storyValidator,
        Logger $logger,
        IpResolver $ipResolver
    ) {
        $this->suggestionModel = $suggestionModel;
        $this->contentLogModel = $contentLogModel;
        $this->storyModel = $storyModel;
        $this->commentModel = $commentModel;
        $this->tagModel = $tagModel;
        $this->userModel = $userModel;
        $this->moderationModel = $moderationModel;
        $this->eventDispatcher = $eventDispatcher;
        $this->tagValidator = $tagValidator;
        $this->storyValidator = $storyValidator;
        $this->logger = $logger;
        $this->ipResolver = $ipResolver;
    }
    
    // =========================================================================
    // ПУБЛИЧНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Добавить новое предложение
     */
    public function addSuggestion(
        string $targetType,
        int $targetId,
        int $userId,
        array $proposedData
    ): int {
        $isModerator = Auth::isModerator() || Auth::isAdmin();

        // 1. Валидация: автор не может предлагать для своего контента (кроме модераторов)
        if (!$isModerator) {
            $this->ensureNotAuthor($targetType, $targetId, $userId);
        }

        // 2. Проверка лимита (кроме модераторов)
        if (!$isModerator) {
            $this->ensureUserLimit($targetType, $targetId, $userId);
        }

        // 3. Валидация данных
        $this->validateProposedData($targetType, $proposedData);

        // 4. Нормализация JSON для консистентного хеширования
        $normalizedJson = $this->normalizeJson($proposedData);

        // 5. Сохранить предложение
        $suggestionId = $this->suggestionModel->create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $userId,
            'proposed_data' => $normalizedJson
        ]);

        // 6. Применить изменения (сразу для модератора или по кворуму)
        if ($isModerator) {
            $proposedData = json_decode($normalizedJson, true);
            $this->applyChanges($targetType, $targetId, $proposedData, $userId, true);
        } else {
            $this->checkAndApplyQuorum($targetType, $targetId, $normalizedJson);
        }

        return $suggestionId;
    }

    /**
     * Получить активные предложения для контента
     */
    public function getActiveSuggestions(string $targetType, int $targetId): array
    {
        return $this->suggestionModel->getActiveSuggestions($targetType, $targetId);
    }

    /**
     * Получить лог изменений для контента
     */
    public function getChangeLog(string $targetType, int $targetId, int $limit = 50): array
    {
        return $this->contentLogModel->getChangeLog($targetType, $targetId, $limit);
    }

    /**
     * Получить количество активных предложений от пользователя
     */
    public function getUserActiveSuggestionsCount(string $targetType, int $targetId, int $userId): int
    {
        return $this->suggestionModel->countUserSuggestions($targetType, $targetId, $userId);
    }
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ МОДЕРАТОРОВ
    // =========================================================================

    /**
     * Получить все активные предложения (для страницы модерации).
     */
    public function getAllActiveSuggestions(int $limit = 30, int $offset = 0, string $filter = ''): array
    {
        $suggestions = $this->suggestionModel->getAllActive($limit, $offset, $filter);

        if (empty($suggestions)) {
            return [];
        }

        // Собираем tag_ids ТОЛЬКО из текущей страницы
        $allTagIds = [];
        foreach ($suggestions as $suggestion) {
            $proposedData = json_decode($suggestion['proposed_data'], true);
            if (!empty($proposedData['tag_ids']) && is_array($proposedData['tag_ids'])) {
                $allTagIds = array_merge($allTagIds, $proposedData['tag_ids']);
            }
        }

        $allTagIds = array_unique(array_map('intval', $allTagIds));

        // Получаем полную информацию о тегах
        $tagsDetails = [];
        if (!empty($allTagIds)) {
            $tagsDetails = $this->tagModel->getDetailsByIds($allTagIds);
        }

        // Добавляем информацию о тегах к каждому предложению
        foreach ($suggestions as &$suggestion) {
            $proposedData = json_decode($suggestion['proposed_data'], true);

            if (!empty($proposedData['tag_ids'])) {
                $tagsForSuggestion = [];
                foreach ($proposedData['tag_ids'] as $tagId) {
                    if (isset($tagsDetails[$tagId])) {
                        $tagsForSuggestion[] = $tagsDetails[$tagId];
                    }
                }
                $suggestion['tags_details'] = $tagsForSuggestion;
            } else {
                $suggestion['tags_details'] = [];
            }
        }

        return $suggestions;
    }

    /**
     * Подсчитать количество активных предложений
     */
    public function countAllActiveSuggestions(string $filter = ''): int
    {
        return $this->suggestionModel->countAllActive($filter);
    }

    /**
     * Одобрить предложение (только для модераторов)
     */
    public function approveSuggestion(int $suggestionId, int $moderatorId): bool
    {
        if (!Auth::isModerator() && !Auth::isAdmin()) {
            throw new Exception("Только модераторы могут одобрять предложения");
        }

        $suggestion = $this->suggestionModel->find($suggestionId);
        if (!$suggestion) {
            throw new Exception("Предложение не найдено");
        }

        $proposedData = json_decode($suggestion['proposed_data'], true);

        $this->applyChanges(
            $suggestion['target_type'],
            $suggestion['target_id'],
            $proposedData,
            $moderatorId,
            true
        );

        $this->logModeratorAction($moderatorId, 'approved_suggestion', $suggestion);

        return true;
    }

    /**
     * Отклонить предложение (только для модераторов)
     */
    public function rejectSuggestion(int $suggestionId, int $moderatorId, string $reason = ''): bool
    {
        if (!Auth::isModerator() && !Auth::isAdmin()) {
            throw new Exception("Только модераторы могут отклонять предложения");
        }

        $suggestion = $this->suggestionModel->find($suggestionId);
        if (!$suggestion) {
            throw new Exception("Предложение не найдено");
        }

        $this->suggestionModel->delete($suggestionId);

        $this->logModeratorAction($moderatorId, 'rejected_suggestion', $suggestion, $reason);

        return true;
    }
    
    // =========================================================================
    // ПРИВАТНЫЕ МЕТОДЫ БИЗНЕС-ЛОГИКИ
    // =========================================================================

    /**
     * Проверить кворум и применить изменения
     */
    private function checkAndApplyQuorum(
        string $targetType,
        int $targetId,
        string $normalizedJson
    ): void {
        $matchingCount = $this->suggestionModel->countMatchingSuggestions(
            $targetType,
            $targetId,
            $normalizedJson
        );

        if ($matchingCount >= self::QUORUM_SIZE) {
            $proposedData = json_decode($normalizedJson, true);
            $this->applyChanges($targetType, $targetId, $proposedData);
        }
    }

    /**
     * Применить изменения к контенту
     */
    private function applyChanges(
        string $targetType,
        int $targetId,
        array $proposedData,
        int $actorId = null,
        bool $isModeratorAction = false
    ): void {
        $model = $this->findModel($targetType);
        $currentData = $model->find($targetId);

        if (!$currentData) {
            throw new Exception("Target not found");
        }

        // Страховочная валидация перед применением
        if (isset($proposedData['tag_ids'])) {
            $validation = $this->tagValidator->validateForSuggestion($proposedData['tag_ids']);
            if (!$validation['valid']) {
                throw new Exception("Cannot apply suggestion: " . $validation['error']);
            }
        }

        // Собираем старые значения для лога
        $oldValues = $this->collectOldValues($targetType, $targetId, $proposedData);

        // Применяем изменения
        foreach ($proposedData as $key => $value) {
            if ($key === 'tag_ids') {
                $this->storyModel->syncTags($targetId, $value);
            } else {
                $model->update($targetId, [$key => $value]);
            }
        }

        // Диспатчим событие
        $this->dispatchContentEvent(new ContentUpdated(
            $targetType,
            $targetId,
            $oldValues,
            $proposedData,
            $isModeratorAction ? 'moderator' : 'community_quorum'
        ));

        // Записываем в лог
        $logText = $this->formatLogText($oldValues, $proposedData, $isModeratorAction);
        $this->contentLogModel->create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'actor_id' => $actorId,
            'action_text' => $logText,
            'is_community_action' => $isModeratorAction ? 0 : 1
        ]);

        // Удаляем все предложения для этого контента
        $this->suggestionModel->deleteAllForTarget($targetType, $targetId);
    }

    /**
     * Собрать старые значения для лога
     */
    private function collectOldValues(string $targetType, int $targetId, array $proposedData): array
    {
        $oldValues = [];
        $model = $this->findModel($targetType);
        $currentData = $model->find($targetId);

        foreach (array_keys($proposedData) as $key) {
            if ($key === 'tag_ids') {
                $oldTagIds = $this->storyModel->getStoryTagIds($targetId);
                $oldValues['tags'] = $this->tagModel->getNamesByIds($oldTagIds);
            } else {
                $oldValues[$key] = $currentData[$key] ?? null;
            }
        }

        return $oldValues;
    }

    /**
     * Логировать действие модератора в таблицу moderations.
     * 
     * ✅ ИЗМЕНЕНО: Все зависимости получены через конструктор,
     * нет статических вызовов и создания моделей через new.
     */
    private function logModeratorAction(
        int $moderatorId,
        string $action,
        array $suggestion,
        string $reason = ''
    ): void {
        try {
            // ✅ Получаем данные модератора через внедрённую модель
            $moderator = $this->userModel->getUser($moderatorId);

            // ✅ Получаем IP через внедрённый IpResolver
            $ipAddress = $this->ipResolver->getClientIp();

            // ✅ Используем внедрённую модель Moderation
            $this->moderationModel->create([
                'user_id'     => $moderatorId,
                'username'    => $moderator['username'] ?? 'Unknown',
                'role'        => $moderator['role'] ?? 'moderator',
                'ip_address'  => $ipAddress,
                'action'      => 'moderation.' . $action,
                'description' => $reason ?: "Модератор {$action} предложение #{$suggestion['id']}",
                'category'    => 'moderation',
                'payload'     => json_encode([
                    'target_type' => strtolower($suggestion['target_type']),
                    'target_id'   => (int) $suggestion['target_id'],
                    'suggestion_id' => (int) $suggestion['id'],
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // ✅ Используем внедрённый Logger вместо статического вызова
            $this->logger->error('Failed to write moderation log: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Форматировать текст для лога
     */
    private function formatLogText(array $oldValues, array $newValues, bool $isModeratorAction = false): string
    {
        $parts = [];

        foreach ($newValues as $key => $newValue) {
            if ($key === 'tag_ids') {
                $oldTags = $oldValues['tags'] ?? [];
                $newTags = $this->tagModel->getNamesByIds($newValue);
                $parts[] = "изменены теги с [" . implode(', ', $oldTags) . "] на [" . implode(', ', $newTags) . "]";
            } else {
                $oldValue = $oldValues[$key] ?? 'null';
                $parts[] = "изменил {$key} с \"{$oldValue}\" на \"{$newValue}\"";
            }
        }

        $source = $isModeratorAction ? "(модератор)" : "(через кворум сообщества)";
        return implode(", ", $parts) . " {$source}";
    }

    /**
     * Найти модель по типу
     * 
     * ✅ ИЗМЕНЕНО: Используем внедрённый commentModel вместо new Comment()
     */
    private function findModel(string $targetType)
    {
        return match ($targetType) {
            'Story' => $this->storyModel,
            'Comment' => $this->commentModel,
            default => throw new Exception("Invalid target type: {$targetType}")
        };
    }

    /**
     * Проверить, что пользователь не автор
     */
    private function ensureNotAuthor(string $targetType, int $targetId, int $userId): void
    {
        $model = $this->findModel($targetType);
        $record = $model->find($targetId);

        if (!$record) {
            throw new Exception("Target not found");
        }

        if ((int) $record['user_id'] === $userId) {
            throw new Exception("Authors cannot suggest changes to their own content");
        }
    }

    /**
     * Проверить лимит предложений от пользователя
     */
    private function ensureUserLimit(string $targetType, int $targetId, int $userId): void
    {
        $count = $this->suggestionModel->countUserSuggestions($targetType, $targetId, $userId);

        if ($count >= self::MAX_USER_SUGGESTIONS) {
            throw new Exception("Вы уже отправили {$count} предложения. Дождитесь их рассмотрения.");
        }
    }

    /**
     * Валидация данных
     */
    private function validateProposedData(string $targetType, array $proposedData): void
    {
        if (empty($proposedData)) {
            throw new Exception("Proposed data cannot be empty");
        }

        $allowedKeys = match ($targetType) {
            'Story' => ['title', 'url', 'description', 'tag_ids'],
            'Comment' => ['text'],
            default => throw new Exception("Invalid target type: {$targetType}")
        };

        foreach (array_keys($proposedData) as $key) {
            if (!in_array($key, $allowedKeys)) {
                throw new Exception("Invalid field for {$targetType} suggestion: {$key}");
            }
        }

        // Валидация количества тегов
        if ($targetType === 'Story') {
            $validation = $this->storyValidator->validateForSuggestion($proposedData);
            if (!$validation['valid']) {
                throw new Exception(implode(' ', $validation['errors']));
            }
        }
    }
    
    // =========================================================================
    // НОРМАЛИЗАЦИЯ JSON
    // =========================================================================

    private function normalizeJson(array $data): string
    {
        $this->sortArrayRecursive($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function sortArrayRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                if ($this->isAssociative($value)) {
                    $this->sortArrayRecursive($value);
                } else {
                    sort($value);
                }
            }
        }
    }

    private function isAssociative(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Безопасно диспатчит событие через EventDispatcher.
     */
    private function dispatchContentEvent(\W3a\Core\Events\Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
