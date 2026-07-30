<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Suggestions\Services\SuggestionService;

/**
 * Контроллер модерации.
 * 
 * Предоставляет интерфейс для модераторов и администраторов:
 * - Публичный лог модерации (доступен всем)
 * - Приватные заметки модераторов о пользователях
 * - Статистика активности модераторов
 * - Бан/разбан пользователей
 * - Рассмотрение предложений по изменениям
 * 
 * Все действия логируются через Audit сервис.
 */
class ModerationsController extends BaseController
{
    /**
     * Получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    // =========================================================================
    // ПУБЛИЧНЫЙ ЛОГ МОДЕРАЦИИ
    // =========================================================================

    /**
     * Публичный лог модерации (GET /mod/log).
     * 
     * Показывает историю всех модераторских действий (категория 'moderation').
     * Доступен всем пользователям для прозрачности модерации.
     * 
     * Пагинация: 30 записей на странице.
     * Для каждой записи декодируется JSON-поле payload.
     */
    public function log(): void
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $auditLog = $this->service(AuditLog::class);
        $items = $auditLog->getByCategory('moderation', $perPage, $offset);
        $total = $auditLog->countByCategory('moderation');
        $pages = max(1, (int)ceil($total / $perPage));

        // Декодируем JSON-поле payload для удобного отображения в шаблоне
        foreach ($items as &$item) {
            $item['decoded_payload'] = !empty($item['payload'])
                ? json_decode($item['payload'], true)
                : [];
        }

        $this->render('log', [
            'title'        => 'Лог модерации',
            'items'        => $items,
            'total'        => $total,
            'pages'        => $pages,
            'current_page' => $page,
        ]);
    }

    // =========================================================================
    // ПРИВАТНЫЕ ЗАМЕТКИ МОДЕРАТОРОВ
    // =========================================================================

    /**
     * Список приватных заметок модераторов (GET /mod/notes).
     * 
     * Заметки — это внутренний инструмент модераторов для фиксации
     * информации о пользователях (предупреждения, история нарушений и т.д.).
     * 
     * Поддерживает фильтрацию по target_user_id через query-параметр.
     */
    public function notes(): void
    {
        $model = $this->service(ModNote::class);
        $notes = $model->getRecentNotes(100);

        $targetUserId = $this->request->query('user_id') !== ''
            ? (int)$this->request->query('user_id')
            : null;

        $this->render('notes', [
            'title'          => 'Модераторские заметки',
            'notes'          => $notes,
            'target_user_id' => $targetUserId,
        ]);
    }

    /**
     * Добавление новой заметки о пользователе.
     */
    public function storeNote(): void
    {
        $userContext = $this->getUserContext();

        try {
            $this->service(ModerationService::class)->addNote(
                (int)$this->request->post('user_id'),
                $userContext['id'],
                (string)($this->request->post('note') ?? ''),
                (int)($this->request->post('is_private') ?? 1)
            );
            
        } catch (\App\Modules\Moderations\Exceptions\ModerationValidationException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirect('/mod/notes');
            return; // Обязательно прерываем выполнение!
            
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.storeNote');
            $this->session()->flash('error', 'Произошла ошибка при добавлении заметки');
            $this->redirect('/mod/notes');
            return; // Обязательно прерываем выполнение!
        }

        $this->session()->flash('success', 'Заметка добавлена');
        $this->redirect('/mod/notes');
    }

    /**
     * Удаление заметки (POST /mod/notes/{id}/delete).
     */
    public function deleteNote(string $id): void
    {
        try {
            $this->service(ModerationService::class)->deleteNote((int)$id);
        } catch (\App\Modules\Moderations\Exceptions\ModerationValidationException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirect('/mod/notes');
            return;
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.deleteNote');
            $this->session()->flash('error', 'Произошла ошибка при удалении заметки');
            $this->redirect('/mod/notes');
            return;
        }

        $this->session()->flash('success', 'Заметка удалена');
        $this->redirect('/mod/notes');
    }

    // =========================================================================
    // СТАТИСТИКА АКТИВНОСТИ
    // =========================================================================

    /**
     * Статистика активности модераторов (GET /mod/stats).
     * 
     * Показывает:
     * - Общую статистику действий за последние 30 дней
     * - Таблицу лидеров (leaderboard) по количеству действий
     */
    public function stats(): void
    {
        $activity = $this->service(ModActivity::class);

        $this->render('stats', [
            'title'       => 'Активность модераторов',
            'stats'       => $activity->getStats(30),
            'leaderboard' => $activity->getLeaderboard(30),
        ]);
    }

    // =========================================================================
    // БАН/РАЗБАН ПОЛЬЗОВАТЕЛЕЙ
    // =========================================================================

    /**
     * Бан или разбан пользователя (POST /mod/ban/{id}).
     * 
     * Принимает параметр 'action' со значениями:
     * - 'ban': заблокировать пользователя с указанием причины
     * - 'unban': разблокировать пользователя
     * 
     * После успешного выполнения редиректит на профиль пользователя.
     */
    public function banUser(string $id): void
    {
        $targetUserId = (int)$id;
        $userContext = $this->getUserContext();
        $action = $this->request->post('action') ?? '';
        $reason = trim($this->request->post('reason') ?? '');

        $service = $this->service(ModerationService::class);
        $result = null;
        $message = '';

        try {
            if ($action === 'ban') {
                $result = $service->banUser($targetUserId, $userContext['id'], $reason);
                $message = "Пользователь «{$result['username']}» забанен";
            } elseif ($action === 'unban') {
                $result = $service->unbanUser($targetUserId, $userContext['id']);
                $message = "Пользователь «{$result['username']}» разбанен";
            } else {
                $this->session()->flash('error', 'Неизвестное действие');
                $this->redirectBack();
                return;
            }
        } catch (\App\Modules\Moderations\Exceptions\ModerationPermissionException | \InvalidArgumentException $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
            return;
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.banUser');
            $this->session()->flash('error', 'Произошла ошибка при выполнении действия');
            $this->redirectBack();
            return;
        }

        $this->session()->flash('success', $message);
        $this->redirect('/user/' . $result['username']);
    }
	

    // =========================================================================
    // РАССМОТРЕНИЕ ПРЕДЛОЖЕНИЙ
    // =========================================================================

    /**
     * Список активных предложений на рассмотрении (GET /mod/suggestions).
     * 
     * Показывает предложения по изменению историй и комментариев,
     * ожидающие решения модераторов.
     * 
     * Поддерживает фильтрацию по типу сущности (Story/Comment)
     * и пагинацию (30 записей на странице).
     * 
     * Также показывает счётчики: общее количество, по историям, по комментариям.
     */
    public function suggestions(): void
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        $filter = $this->request->query('type', '');

        $suggestionService = $this->service(SuggestionService::class);

        $suggestions = $suggestionService->getAllActiveSuggestions($perPage, $offset, $filter);
        $total = $suggestionService->countAllActiveSuggestions($filter);
        $pages = max(1, (int)ceil($total / $perPage));

        // Счётчики для фильтров в шаблоне
        $totalCount = $suggestionService->countAllActiveSuggestions('');
        $storiesCount = $suggestionService->countAllActiveSuggestions('Story');
        $commentsCount = $suggestionService->countAllActiveSuggestions('Comment');

        $this->render('suggestions', [
            'title' => 'Предложения на рассмотрении',
            'suggestions' => $suggestions,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
            'filter' => $filter,
            'totalCount' => $totalCount,
            'storiesCount' => $storiesCount,
            'commentsCount' => $commentsCount
        ]);
    }

    /**
     * Одобрение предложения (POST /mod/suggestions/{id}/approve).
     * 
     * Применяет предложенные изменения к сущности (истории или комментарию).
     * Действие выполняется от имени текущего модератора и логируется.
     * 
     * При ошибке (например, если предложение уже обработано) показывает
     * flash-сообщение с текстом ошибки.
     */
    public function approveSuggestion(string $id): void
    {
        $suggestionId = (int)$id;
        $userContext = $this->getUserContext();

        try {
            $this->service(SuggestionService::class)
                ->approveSuggestion($suggestionId, $userContext['id']);

            $this->redirectWithMessage('/mod/suggestions', 'Предложение одобрено и применено.', 'success');
        } catch (\Exception $e) {
            $this->redirectWithMessage('/mod/suggestions', $e->getMessage(), 'error');
        }
    }

    /**
     * Отклонение предложения (POST /mod/suggestions/{id}/reject).
     * 
     * Отклоняет предложение с указанием причины. Предложение остаётся в базе,
     * но помечается как отклонённое.
     * 
     * Действие выполняется от имени текущего модератора и логируется.
     */
    public function rejectSuggestion(string $id): void
    {
        $suggestionId = (int)$id;
        $reason = trim($this->request->post('reason', ''));
        $userContext = $this->getUserContext();

        try {
            $this->service(SuggestionService::class)
                ->rejectSuggestion($suggestionId, $userContext['id'], $reason);

            $this->redirectWithMessage('/mod/suggestions', 'Предложение отклонено.', 'success');
        } catch (\Exception $e) {
            $this->redirectWithMessage('/mod/suggestions', $e->getMessage(), 'error');
        }
    }
}
