<?php

declare(strict_types=1);

namespace App\Modules\Muted\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Support\MessageBag;

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
    public function list(): ViewResponse
    {
        $userContext = $this->getUserContext();
        $muteService = $this->service(MuteService::class);
        $mutedUsers = $muteService->getMutedList($userContext['id']);

        return $this->render('list', [
            'title' => 'Игнорируемые пользователи',
            'mutedUsers' => $mutedUsers,
            'currentUserId' => $userContext['id'],
        ]);
    }

    /**
     * Переключение статуса игнорирования пользователя.
     */
    public function toggle(string $id): Response
    {
        $userContext = $this->getUserContext();
        $targetUserId = (int)$id;
        $isAjax = $this->request->isAjaxRequest();

        // Проверяем, что целевой пользователь существует
        $userModel = $this->container->get(User::class);
        $targetUser = $userModel->find($targetUserId);

        if (!$targetUser) {
            if ($isAjax) {
                return $this->json(['error' => 'Пользователь не найден'], 404);
            }
            MessageBag::flashMessage('error', 'Пользователь не найден');
            return $this->redirectBack();
        }

        $isMuted = null;
        $message = '';
        $muteService = $this->service(MuteService::class);

        try {
            // Переключаем статус игнорирования
            $isMuted = $muteService->toggle($userContext['id'], $targetUserId);

            // Формируем сообщение на основе результата
            $message = $isMuted
                ? "Пользователь {$targetUser['username']} добавлен в игнор-лист"
                : "Пользователь {$targetUser['username']} удалён из игнор-листа";

        } catch (MuteValidationException $e) {
            // Ловим бизнес-ошибки (например, "Нельзя игнорировать самого себя")
            if ($isAjax) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
            
        } catch (\Throwable $e) {
            // Ловим реальные непредвиденные ошибки
            $this->logError($e, 'Mute.toggle');
            if ($isAjax) {
                return $this->json(['error' => 'Произошла ошибка сервера'], 500);
            }
            MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка');
            return $this->redirectBack();
        }

        if ($isAjax) {
            return $this->json([
                'success' => true,
                'is_muted' => $isMuted,
                'username' => $targetUser['username'],
                'message' => $message,
            ]);
        }

        MessageBag::flashMessage('success', $message);
        return $this->redirect('/muted');
    }
}