<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Services;

use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Users\Models\User;
use W3a\Core\Events\EventDispatcher;
use App\Modules\Users\Events\UserBanned;
use App\Modules\Users\Events\UserUnbanned;
use App\Modules\Moderations\Events\ModNoteAdded;
use App\Modules\Moderations\Exceptions\ModerationValidationException;
use App\Modules\Moderations\Exceptions\ModerationPermissionException;

/**
 * Сервис для работы с модерацией.
 * Отвечает за управление заметками, бан/разбан пользователей и логирование действий.
 */
class ModerationService
{
    private Moderation $moderationModel;
    private ModNote $modNoteModel;
    private User $userModel;
    private EventDispatcher $eventDispatcher;

    public function __construct(
        Moderation $moderationModel,
        ModNote $modNoteModel,
        User $userModel,
        EventDispatcher $eventDispatcher
    ) {
        $this->moderationModel = $moderationModel;
        $this->modNoteModel = $modNoteModel;
        $this->userModel = $userModel;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Добавляет приватную заметку модератора о пользователе.
     *
     * @throws ModerationValidationException Если данные невалидны
     * @throws \RuntimeException Если не удалось сохранить заметку
     */
    public function addNote(int $targetUserId, int $moderatorId, string $noteText, int $isPrivate = 1): array
    {
        $noteText = trim($noteText);

        if ($targetUserId <= 0 || $noteText === '') {
            throw new ModerationValidationException('Укажите пользователя и текст заметки');
        }

        $noteId = $this->modNoteModel->create([
            'user_id' => $targetUserId,
            'moderator_id' => $moderatorId,
            'note' => $noteText,
            'is_private' => $isPrivate,
        ]);

        if (!$noteId) {
            throw new \RuntimeException('Не удалось сохранить заметку');
        }

        $this->eventDispatcher->dispatch(new ModNoteAdded(
            $moderatorId,
            $targetUserId,
            mb_substr($noteText, 0, 200)
        ));

        return [
            'note_id' => $noteId,
            'user_id' => $targetUserId,
        ];
    }

    /**
     * Удаляет заметку модератора.
     *
     * @throws ModerationValidationException Если ID заметки некорректен
     */
    public function deleteNote(int $noteId): bool
    {
        if ($noteId <= 0) {
            throw new ModerationValidationException('Некорректный ID заметки');
        }

        $this->modNoteModel->deleteNote($noteId);

        return true;
    }

    /**
     * Блокирует пользователя.
     *
     * @throws ModerationPermissionException Если модератор пытается забанить себя
     * @throws \InvalidArgumentException Если пользователь не найден
     */
    public function banUser(int $targetUserId, int $moderatorId, string $reason): array
    {
        if ($targetUserId === $moderatorId) {
            throw new ModerationPermissionException('Вы не можете применить это действие к себе');
        }

        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            throw new \InvalidArgumentException('Пользователь не найден');
        }

        $this->moderationModel->banUser($targetUserId, $moderatorId, $reason);

        $this->eventDispatcher->dispatch(new UserBanned(
            $targetUserId,
            $moderatorId,
            $reason ?: 'Без указания причины'
        ));

        return [
            'user' => $targetUser,
            'username' => $targetUser['username'],
        ];
    }

    /**
     * Разблокирует пользователя.
     *
     * @throws ModerationPermissionException Если модератор пытается разбанить себя
     * @throws \InvalidArgumentException Если пользователь не найден
     */
    public function unbanUser(int $targetUserId, int $moderatorId): array
    {
        if ($targetUserId === $moderatorId) {
            throw new ModerationPermissionException('Вы не можете применить это действие к себе');
        }

        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            throw new \InvalidArgumentException('Пользователь не найден');
        }

        $this->moderationModel->unbanUser($targetUserId);

        $this->eventDispatcher->dispatch(new UserUnbanned(
            $targetUserId,
            $moderatorId,
            'Разбан пользователя'
        ));

        return [
            'user' => $targetUser,
            'username' => $targetUser['username'],
        ];
    }
}