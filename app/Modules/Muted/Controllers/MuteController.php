<?php

declare(strict_types=1);

namespace App\Modules\Muted\Controllers;

use App\BaseController;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Muted\Exceptions\MuteValidationException;
use App\Modules\Users\Models\User;

/**
 * Контроллер управления игнорируемыми пользователями (mute).
 * 
 * Все маршруты защищены middleware ['web', 'auth'].
 */
class MuteController extends BaseController
{
    /**
     * Список игнорируемых пользователей.
     */
    public function list(): void
    {
        $userContext = $this->getUserContext();
        $muteService = $this->service(MuteService::class);
        $mutedUsers = $muteService->getMutedList($userContext['id']);

        $this->render('list', [
            'title' => 'Игнорируемые пользователи',
            'mutedUsers' => $mutedUsers,
            'currentUserId' => $userContext['id'],
        ]);
    }

    /**
     * Переключение статуса игнорирования пользователя.
     */
    public function toggle(string $id): void
    {
        $userContext = $this->getUserContext();
        $targetUserId = (int)$id;
        $isAjax = $this->request->isAjaxRequest();

        // Проверяем, что целевой пользователь существует
        $userModel = $this->container->get(User::class);
        $targetUser = $userModel->find($targetUserId);

        if (!$targetUser) {
            if ($isAjax) {
                $this->json(['error' => 'Пользователь не найден'], 404);
                return;
            }
            $this->redirectBack();
            return;
        }

        $isMuted = null;
        $message = '';

        try {
            // Переключаем статус игнорирования
            $muteService = $this->service(MuteService::class);
            $isMuted = $muteService->toggle($userContext['id'], $targetUserId);

            // Формируем сообщение на основе результата
            $message = $isMuted
                ? "Пользователь {$targetUser['username']} добавлен в игнор-лист"
                : "Пользователь {$targetUser['username']} удалён из игнор-листа";

        } catch (MuteValidationException $e) {
            // Ловим бизнес-ошибки (например, "Нельзя игнорировать самого себя")
            if ($isAjax) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $this->backWithMessage($e->getMessage(), 'error');
            return;
            
        } catch (\Throwable $e) {
            // Ловим реальные непредвиденные ошибки
            $this->logError($e, 'Mute.toggle');
            if ($isAjax) {
                $this->json(['error' => 'Произошла ошибка сервера'], 500);
                return;
            }
            $this->backWithMessage('Произошла непредвиденная ошибка', 'error');
            return;
        }

        if ($isAjax) {
            $this->json([
                'success' => true,
                'is_muted' => $isMuted,
                'username' => $targetUser['username'],
                'message' => $message,
            ]);
            return;
        }

        $this->redirectWithMessage('/muted', $message, 'success');
    }
}