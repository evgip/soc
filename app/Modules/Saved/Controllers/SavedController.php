<?php

declare(strict_types=1);

namespace App\Modules\Saved\Controllers;

use App\BaseController;
use W3a\Core\Session;
use App\Modules\Saved\Models\SavedStory;
use App\Modules\Saved\Services\SavedService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Votes\Models\Vote;

/**
 * Контроллер сохранённых историй (закладок).
 */
class SavedController extends BaseController
{
    /**
     * Получить экземпляр Session из DI-контейнера.
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * Список сохранённых историй
     */
    public function index(): void
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            $this->redirect('/login');
            return;
        }

        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $savedModel = $this->container->get(SavedStory::class);
        $stories = $savedModel->getUserSaved($userContext['id'], $perPage, $offset);
        $totalStories = $savedModel->getUserSavedCount($userContext['id']);
        $totalPages = (int)ceil($totalStories / $perPage);

        $storyIds = array_column($stories, 'id');
        $filterService = $this->service(StoryFilterService::class);
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        $currentVotes = [];
        if (!empty($storyIds)) {
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
        }

        $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $filterService->getBannedDomains(),
            'sort' => 'saved',
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
            'currentVotes' => $currentVotes,
            'title' => 'Мои закладки',
        ]);
    }

    /**
     * AJAX / POST toggle закладки
     */
    public function toggle(string $id): void
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['error' => 'Необходима авторизация'], 401);
                return;
            }
            $this->redirect('/login');
            return;
        }

        $storyId = (int)$id;
        $savedService = $this->service(SavedService::class);
        
        // ✅ Сервис возвращает чистый бизнес-факт: добавлено (true) или удалено (false)
        $isSaved = $savedService->toggle($userContext['id'], $storyId);

        // ✅ Контроллер принимает решение о UI на основе этого факта
        $message = $isSaved ? 'История добавлена в закладки' : 'История удалена из закладок';

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json([
                'success' => true,
                'is_saved' => $isSaved,
                'message' => $message // Фронтенд может использовать это для toast-уведомления
            ]);
            return;
        }

        $this->session()->flash('success', $message);
        $referer = $_SERVER['HTTP_REFERER'] ?? '/saved';
        $this->redirectBack($referer);
    }
}