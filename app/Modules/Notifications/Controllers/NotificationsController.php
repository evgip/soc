<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\BaseController;
use W3a\Core\Exceptions\JsonResponseException;
use App\Modules\Notifications\Services\NotificationService;

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
    public function index(): void
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

        $this->render('index', [
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
     * 
     * ВАЖНО: try-catch НЕ перехватывает JsonResponseException,
     * чтобы он корректно обрабатывался в Application.
     */
    public function markAsRead(string $id): void
    {
        $userContext = $this->getUserContext();
        $notificationId = (int)$id;

        try {
            $success = $this->service(NotificationService::class)->markAsRead($notificationId, $userContext['id']);

            $this->json([
                'success' => $success,
                'message' => $success ? 'Отмечено как прочитанное' : 'Не удалось отметить'
            ]);
        } catch (JsonResponseException $e) {
            // НЕ перехватываем JsonResponseException — пусть Application обработает
            throw $e;
        } catch (\Throwable $e) {
            // Логируем реальную ошибку
            $this->logError($e);
            
            $this->json([
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
    public function markAllAsRead(): void
    {
        $userContext = $this->getUserContext();

        try {
            $success = $this->service(NotificationService::class)->markAllAsRead($userContext['id']);

            $this->json([
                'success' => $success,
                'message' => $success ? 'Все уведомления отмечены' : 'Ошибка'
            ]);
        } catch (JsonResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError($e);
            
            $this->json([
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
    public function getCount(): void
    {
        $userContext = $this->getUserContext();

        try {
            $count = $this->service(NotificationService::class)->getUnreadCount($userContext['id']);
            $this->json(['count' => $count]);
        } catch (JsonResponseException $e) {
            // НЕ перехватываем JsonResponseException
            throw $e;
        } catch (\Throwable $e) {
            // Логируем реальную ошибку
            $this->logError($e);
            
            $this->json(['count' => 0], 500);
        }
    }

}