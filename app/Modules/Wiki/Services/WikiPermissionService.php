<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Services;

use App\Modules\Wiki\Models\WikiPermission;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;
use W3a\Core\Audit;
use W3a\Core\Security\UserContext;
use App\Modules\Wiki\Exceptions\WikiPermissionException;

/**
 * Сервис для управления правами доступа к wiki.
 * Отвечает за проверку и выдачу прав пользователям на wiki для конкретных тегов.
 */
class WikiPermissionService
{
    private WikiPermission $wikiPermission;
    private Tag $tag;
    private User $userModel;
    private Audit $audit;
    private UserContext $currentUser;

    public function __construct(
        WikiPermission $wikiPermission,
        Tag $tag,
        User $userModel,
        Audit $audit,
        UserContext $currentUser
    ) {
        $this->wikiPermission = $wikiPermission;
        $this->tag = $tag;
        $this->userModel = $userModel;
        $this->audit = $audit;
        $this->currentUser = $currentUser;
    }

    public function canCreateWikiForTag(int $tagId, int $userId): bool
    {
        if ($this->currentUser->canModerate()) {
            return true;
        }

        $tag = $this->tag->find($tagId);
        if ($tag && isset($tag['user_id']) && (int)$tag['user_id'] === $userId) {
            return true;
        }

        $permission = $this->wikiPermission->getUserPermission($tagId, $userId);
        return $permission && $permission['can_edit'];
    }

    public function canEditPage(array $page, int $userId): bool
    {
        if ($this->currentUser->canModerate()) {
            return true;
        }

        if ((int)$page['author_id'] === $userId) {
            return true;
        }

        if (!empty($page['tag_id'])) {
            return $this->canCreateWikiForTag((int)$page['tag_id'], $userId);
        }

        return false;
    }

    public function canDeletePage(array $page, int $userId): bool
    {
        if ($this->currentUser->canModerate()) {
            return true;
        }

        if (!empty($page['tag_id'])) {
            $tag = $this->tag->find((int)$page['tag_id']);

            if ($tag && isset($tag['user_id']) && (int)$tag['user_id'] === $userId) {
                return true;
            }

            $permission = $this->wikiPermission->getUserPermission((int)$page['tag_id'], $userId);
            return $permission && $permission['can_delete'];
        }

        return (int)$page['author_id'] === $userId;
    }

    /**
     * Выдает права пользователю на редактирование wiki тега.
     *
     * @throws WikiPermissionException Если у текущего пользователя нет прав на выдачу
     * @throws \InvalidArgumentException Если целевой пользователь не найден
     */
    public function grantPermission(
        int $tagId, 
        string $username, 
        int $grantedBy, 
        bool $canEdit = true, 
        bool $canDelete = false
    ): bool {
        $tag = $this->tag->find($tagId);
        $isTagAuthor = $tag && isset($tag['user_id']) && (int)$tag['user_id'] === $grantedBy;

        if (!$isTagAuthor && !$this->currentUser->isAdmin) {
            throw new WikiPermissionException('Только автор тега или администратор может выдавать права');
        }

        $targetUser = $this->userModel->findByName($username);
        if (!$targetUser) {
            throw new \InvalidArgumentException('Пользователь не найден');
        }

        $targetUserId = (int)$targetUser['id'];
        $existing = $this->wikiPermission->getUserPermission($tagId, $targetUserId);

        if ($existing) {
            $result = (bool)$this->wikiPermission->update((int)$existing['id'], [
                'can_edit' => $canEdit ? 1 : 0,
                'can_delete' => $canDelete ? 1 : 0
            ]);
        } else {
            $result = (bool)$this->wikiPermission->create([
                'tag_id' => $tagId,
                'user_id' => $targetUserId,
                'can_edit' => $canEdit ? 1 : 0,
                'can_delete' => $canDelete ? 1 : 0,
                'granted_by' => $grantedBy
            ]);
        }

        $this->audit->log('wiki.permission_granted', "Выданы права на wiki для тега ID: {$tagId}", 'wiki', [
            'tag_id' => $tagId,
            'user_id' => $targetUserId,
            'granted_by' => $grantedBy,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete
        ]);

        return $result;
    }

    /**
     * Отзывает права пользователя на wiki тега.
     *
     * @throws WikiPermissionException Если у текущего пользователя нет прав на отзыв
     * @throws \InvalidArgumentException Если права не найдены
     */
    public function revokePermission(int $tagId, int $targetUserId, int $revokedBy): bool
    {
        $tag = $this->tag->find($tagId);
        $isTagAuthor = $tag && isset($tag['user_id']) && (int)$tag['user_id'] === $revokedBy;

        if (!$isTagAuthor && !$this->currentUser->isAdmin) {
            throw new WikiPermissionException('Только автор тега или администратор может отзывать права');
        }

        $permission = $this->wikiPermission->getUserPermission($tagId, $targetUserId);
        if (!$permission) {
            throw new \InvalidArgumentException('Права не найдены');
        }

        $result = $this->wikiPermission->delete((int)$permission['id']);

        $this->audit->log('wiki.permission_revoked', "Отозваны права на wiki для тега ID: {$tagId}", 'wiki', [
            'tag_id' => $tagId,
            'user_id' => $targetUserId,
            'revoked_by' => $revokedBy
        ]);

        return $result;
    }

    public function getTagEditors(int $tagId): array
    {
        return $this->wikiPermission->getTagEditors($tagId);
    }
}