<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Support\MessageBag; // 🔥 Добавили использование MessageBag

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
    // 🔥 УДАЛЕНО: метод session() больше не нужен

    // =========================================================================
    // ПУБЛИЧНЫЙ ЛОГ МОДЕРАЦИИ
    // =========================================================================

    /**
     * Публичный лог модерации (GET /mod/log).
     */
    public function log(): ViewResponse
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $auditLog = $this->service(AuditLog::class);
        $items = $auditLog->getByCategory('moderation', $perPage, $offset);
        $total = $auditLog->countByCategory('moderation');
        $pages = max(1, (int)ceil($total / $perPage));

        foreach ($items as &$item) {
            $item['decoded_payload'] = !empty($item['payload'])
                ? json_decode($item['payload'], true)
                : [];
        }

        return $this->render('log', [
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
     */
    public function notes(): ViewResponse
    {
        $model = $this->service(ModNote::class);
        $notes = $model->getRecentNotes(100);

        $targetUserId = $this->request->query('user_id') !== ''
            ? (int)$this->request->query('user_id')
            : null;

        return $this->render('notes', [
            'title'          => 'Модераторские заметки',
            'notes'          => $notes,
            'target_user_id' => $targetUserId,
        ]);
    }

    /**
     * Добавление новой заметки о пользователе.
     */
    public function storeNote(): RedirectResponse
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
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect('/mod/notes');
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.storeNote');
            MessageBag::flashMessage('error', 'Произошла ошибка при добавлении заметки');
            return $this->redirect('/mod/notes');
        }

        MessageBag::flashMessage('success', 'Заметка добавлена');
        return $this->redirect('/mod/notes');
    }

    /**
     * Удаление заметки (POST /mod/notes/{id}/delete).
     */
    public function deleteNote(string $id): RedirectResponse
    {
        try {
            $this->service(ModerationService::class)->deleteNote((int)$id);
        } catch (\App\Modules\Moderations\Exceptions\ModerationValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect('/mod/notes');
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.deleteNote');
            MessageBag::flashMessage('error', 'Произошла ошибка при удалении заметки');
            return $this->redirect('/mod/notes');
        }

        MessageBag::flashMessage('success', 'Заметка удалена');
        return $this->redirect('/mod/notes');
    }

    // =========================================================================
    // СТАТИСТИКА АКТИВНОСТИ
    // =========================================================================

    /**
     * Статистика активности модераторов (GET /mod/stats).
     */
    public function stats(): ViewResponse
    {
        $activity = $this->service(ModActivity::class);

        return $this->render('stats', [
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
     */
    public function banUser(string $id): RedirectResponse
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
                MessageBag::flashMessage('error', 'Неизвестное действие');
                return $this->redirectBack();
            }
        } catch (\App\Modules\Moderations\Exceptions\ModerationPermissionException | \InvalidArgumentException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectBack();
        } catch (\Throwable $e) {
            $this->logError($e, 'Moderations.banUser');
            MessageBag::flashMessage('error', 'Произошла ошибка при выполнении действия');
            return $this->redirectBack();
        }

        MessageBag::flashMessage('success', $message);
        return $this->redirect('/user/' . $result['username']);
    }

    // =========================================================================
    // РАССМОТРЕНИЕ ПРЕДЛОЖЕНИЙ
    // =========================================================================

    /**
     * Список активных предложений на рассмотрении (GET /mod/suggestions).
     */
    public function suggestions(): ViewResponse
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        $filter = $this->request->query('type', '');

        $suggestionService = $this->service(SuggestionService::class);

        $suggestions = $suggestionService->getAllActiveSuggestions($perPage, $offset, $filter);
        $total = $suggestionService->countAllActiveSuggestions($filter);
        $pages = max(1, (int)ceil($total / $perPage));

        $totalCount = $suggestionService->countAllActiveSuggestions('');
        $storiesCount = $suggestionService->countAllActiveSuggestions('Story');
        $commentsCount = $suggestionService->countAllActiveSuggestions('Comment');

        return $this->render('suggestions', [
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
     */
    public function approveSuggestion(string $id): RedirectResponse
    {
        $suggestionId = (int)$id;
        $userContext = $this->getUserContext();

        try {
            $this->service(SuggestionService::class)->approveSuggestion($suggestionId, $userContext['id']);
            MessageBag::flashMessage('success', 'Предложение одобрено и применено.');
            return $this->redirect('/mod/suggestions');
        } catch (\Exception $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect('/mod/suggestions');
        }
    }

    /**
     * Отклонение предложения (POST /mod/suggestions/{id}/reject).
     */
    public function rejectSuggestion(string $id): RedirectResponse
    {
        $suggestionId = (int)$id;
        $reason = trim($this->request->post('reason', ''));
        $userContext = $this->getUserContext();

        try {
            $this->service(SuggestionService::class)->rejectSuggestion($suggestionId, $userContext['id'], $reason);
            MessageBag::flashMessage('success', 'Предложение отклонено.');
            return $this->redirect('/mod/suggestions');
        } catch (\Exception $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirect('/mod/suggestions');
        }
    }
}