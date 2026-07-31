<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Support\Validator;
use W3a\Core\Support\Audit;
use W3a\Core\Events\EventDispatcher;
use W3a\Core\Security\UserContext;

use App\Modules\Stories\Models\Story;
use App\Modules\Origins\Models\Domain;
use App\Modules\Stories\Events\StoryDeleted;
use App\Modules\Stories\Events\StoryRestored;
use App\Modules\Stories\Exceptions\StoryValidationException;
use App\Modules\Stories\Exceptions\BannedDomainException;

/**
 * Сервис для управления бизнес-логикой историй.
 * Отвечает за валидацию, создание, обновление и удаление публикаций.
 */
class StoryService
{
    private Story $storyModel;
    private Domain $domainModel;
    private StoryValidator $storyValidator;
    private Validator $validator;
    private Audit $audit;
    private EventDispatcher $eventDispatcher;
    private UserContext $currentUser;

    public function __construct(
        Story $storyModel,
        Domain $domainModel,
        StoryValidator $storyValidator,
        Validator $validator,
        Audit $audit,
        EventDispatcher $eventDispatcher,
        UserContext $currentUser
    ) {
        $this->storyModel = $storyModel;
        $this->domainModel = $domainModel;
        $this->storyValidator = $storyValidator;
        $this->validator = $validator;
        $this->audit = $audit;
        $this->eventDispatcher = $eventDispatcher;
        $this->currentUser = $currentUser;
    }

    /**
     * Создаёт новую историю.
     *
     * @throws StoryValidationException Если данные не прошли валидацию
     * @throws BannedDomainException Если домен заблокирован
     */
    public function createStory(array $data, int $userId): int
    {
        $validation = $this->storyValidator->validate($data, false);
        if (!$validation['valid']) {
            throw new StoryValidationException(implode(' ', $validation['errors']));
        }

        $domain = !empty($data['url']) ? parse_url($data['url'], PHP_URL_HOST) : null;
        $this->checkBannedDomain($domain, $userId, $data['url'] ?? '');

        $storyData = [
            'user_id' => $userId,
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'domain' => $domain,
            'description' => $data['description'] ?? null,
            'score' => 1,
            'comments_count' => 0,
            'user_is_following' => isset($data['user_is_following']) ? 1 : 0,
        ];

        $storyId = $this->storyModel->create($storyData);

        if ($storyId > 0 && !empty($data['tags'])) {
            $this->storyModel->syncTags($storyId, $data['tags']);
            $this->storyModel->recalculateHotness($storyId);
        }

        $this->audit->log('story.created', 'Пользователь создал новую публикацию', 'story', [
            'story_id' => $storyId,
            'user_id' => $userId
        ]);

        return $storyId;
    }

    /**
     * Обновляет существующую историю.
     *
     * @throws \InvalidArgumentException Если история не найдена
     * @throws StoryValidationException Если данные не прошли валидацию
     * @throws BannedDomainException Если новый домен заблокирован
     */
    public function updateStory(int $storyId, array $data): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            throw new \InvalidArgumentException("Публикация не найдена.");
        }

        $validation = $this->storyValidator->validate($data, true);
        if (!$validation['valid']) {
            throw new StoryValidationException(implode(' ', $validation['errors']));
        }

        $domain = !empty($data['url']) ? parse_url($data['url'], PHP_URL_HOST) : null;
        $this->checkBannedDomain($domain, (int)$story['user_id'], $data['url'] ?? '');
        
        $updateData = [
            'title' => $data['title'] ?? $story['title'],
            'url' => $data['url'] ?? $story['url'],
            'description' => $data['description'] ?? $story['description'],
            'domain' => $domain,
            'user_is_following' => isset($data['user_is_following']) ? 1 : 0,
        ];

        $this->storyModel->update($storyId, $updateData);

        if (isset($data['tags'])) {
            $this->storyModel->syncTags($storyId, $data['tags']);
        }

        return true;
    }

    /**
     * Проверяет наличие прав на редактирование истории.
     */
    public function canEditStory(array $story): bool
    {
        $isAuthor = (int)$story['user_id'] === $this->currentUser->id;
        return $isAuthor || $this->currentUser->canModerate();
    }

    /**
     * Проверяет статус домена и логирует попытки публикации с заблокированных доменов.
     *
     * @throws BannedDomainException
     */
    private function checkBannedDomain(?string $domain, int $userId, string $url): void
    {
        if (empty($domain)) {
            return;
        }

        if (!$this->domainModel->isBanned($domain)) {
            return;
        }

        $banInfo = $this->domainModel->getBanInfo($domain);
        $reason = $banInfo['ban_reason'] ?? 'Домен заблокирован администрацией';

        $this->audit->log('story.rejected_banned_domain', "Попытка публикации с забаненного домена", 'story', [
            'domain' => $domain,
            'user_id' => $userId,
            'url' => $url,
            'reason' => $reason,
        ]);

        throw new BannedDomainException(
            "Публикация отклонена: домен **" . e($domain) . "** заблокирован. Причина: " . e($reason)
        );
    }

    /**
     * Скрывает историю.
     *
     * @throws \InvalidArgumentException Если история не найдена
     */
    public function deleteStory(int $storyId, int $adminId, string $reason = 'История скрыта модератором'): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            throw new \InvalidArgumentException("Публикация не найдена.");
        }

        $this->storyModel->delete($storyId);
        $this->eventDispatcher->dispatch(new StoryDeleted($storyId, $adminId, $reason));
        
        return true;
    }

    /**
     * Восстанавливает скрытую историю.
     *
     * @throws \InvalidArgumentException Если история не найдена
     */
    public function restoreStory(int $storyId, int $adminId): bool
    {
        $story = $this->storyModel->find($storyId, withTrashed: true);
        if (!$story) {
            throw new \InvalidArgumentException("Публикация не найдена.");
        }

        $this->storyModel->restore($storyId);
        $this->eventDispatcher->dispatch(new StoryRestored($storyId, $adminId));
        
        return true;
    }
}