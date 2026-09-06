<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\JsonResponse;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Common\Support\Layout;

/**
 * Контроллер уведомлений пользователя.
 * 
 * Обрабатывает:
 * - Список уведомлений с фильтрацией по типу и пагинацией
 * - Отметку одного уведомления как прочитанного (AJAX)
 * - Отметку всех уведомлений как прочитанных (AJAX)
 * - API для получения счётчика непрочитанных уведомлений
 * 
 * Все маршруты защищены middleware ['web', 'auth'],
 * поэтому проверки авторизации в контроллере не требуются.
 */
class NotificationsController extends BaseController
{
    // =========================================================================
    // СПИСОК УВЕДОМЛЕНИЙ
    // =========================================================================

    /**
     * Страница списка уведомлений (GET /notifications).
     */
    public function index(): ViewResponse
    {
        $userContext = $this->getUserContext();



        $type = (string)$this->request->getParams('type', 'all');
        $page = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.notifications_per_page', 25, 'int');

        $data = $this->service(NotificationService::class)->getNotificationsForIndex(
            $userContext['id'],
            $type,
            $page,
            $perPage
        );

        return $this->render('index', [
            'title' => 'Уведомления',
            'notifications' => $data['notifications'],
            'currentType' => $data['currentType'],
            'counts' => $data['counts'],
            'totalUnread' => $data['totalUnread'],
            'currentPage' => $page,
            'request' => $this->request,
        ]);
    }

    // =========================================================================
    // ОТМЕТКА ОДНОГО УВЕДОМЛЕНИЯ КАК ПРОЧИТАННОГО
    // =========================================================================

    /**
     * Отметка одного уведомления как прочитанного (POST /notifications/{id}/read).
     * 
     * AJAX endpoint. Возвращает JSON с результатом операции.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $userContext = $this->getUserContext();
        $notificationId = (int)$id;

        try {
            $success = $this->service(NotificationService::class)->markAsRead($notificationId, $userContext['id']);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Отмечено как прочитанное' : 'Не удалось отметить'
            ]);
        } catch (\Throwable $e) {
            // Логируем реальную ошибку и возвращаем 500
            $this->logError($e, 'Notifications.markAsRead');
            
            return $this->json([
                'success' => false, 
                'message' => 'Ошибка сервера'
            ], 500);
        }
    }

    // =========================================================================
    // ОТМЕТКА ВСЕХ УВЕДОМЛЕНИЙ КАК ПРОЧИТАННЫХ
    // =========================================================================

    /**
     * Отметка всех уведомлений пользователя как прочитанных (POST /notifications/mark-all-read).
     */
    public function markAllAsRead(): JsonResponse
    {
        $userContext = $this->getUserContext();

        try {
            $success = $this->service(NotificationService::class)->markAllAsRead($userContext['id']);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Все уведомления отмечены' : 'Ошибка'
            ]);
        } catch (\Throwable $e) {
            $this->logError($e, 'Notifications.markAllAsRead');
            
            return $this->json([
                'success' => false, 
                'message' => 'Ошибка сервера'
            ], 500);
        }
    }

    // =========================================================================
    // API: СЧЁТЧИК НЕПРОЧИТАННЫХ
    // =========================================================================

    /**
     * Получение количества непрочитанных уведомлений (GET /api/notifications/count).
     * 
     * AJAX endpoint для обновления счётчика в шапке сайта.
     */
    public function getCount(): JsonResponse
    {
        $userContext = $this->getUserContext();

        try {
            $count = $this->service(NotificationService::class)->getUnreadCount($userContext['id']);
            return $this->json(['count' => $count]);
        } catch (\Throwable $e) {
            // Логируем реальную ошибку и возвращаем 0 с кодом 500
            $this->logError($e, 'Notifications.getCount');
            
            return $this->json(['count' => 0], 500);
        }
    }
}
