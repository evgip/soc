<?php

declare(strict_types=1);

namespace App\Modules\Library\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use App\Modules\Common\Support\Layout;

class LibraryController extends BaseController
{
    public function index(): Response
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            return $this->redirect('/login');
        }

        $tab = (string)($this->request->getParams('tab') ?? 'saved');
        if (!in_array($tab, ['saved', 'responses', 'history', 'collections'], true)) {
            $tab = 'saved';
        }

        Layout::set(Layout::FULL);

        $data = [
            'title' => 'Библиотека',
            'activeTab' => $tab,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'currentUsername' => '',
        ];

        // Получаем username для ссылок на коллекции
        if ($userContext['id']) {
            $userModel = $this->container->get(\App\Modules\Users\Models\User::class);
            $user = $userModel->find($userContext['id']);
            $data['currentUsername'] = $user['username'] ?? '';
        }

        if ($tab === 'saved') {
            return $this->savedTab($data);
        }

        if ($tab === 'responses') {
            return $this->responsesTab($data);
        }

        if ($tab === 'collections') {
            return $this->collectionsTab($data);
        }

        return $this->historyTab($data);
    }

    private function savedTab(array $data): ViewResponse
    {
        $userContext = $this->getUserContext();

        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $savedModel = $this->container->get(\App\Modules\Saved\Models\SavedStory::class);
        $stories = $savedModel->getUserSaved($userContext['id'], $perPage, $offset);
        $totalStories = $savedModel->getUserSavedCount($userContext['id']);
        $totalPages = (int)ceil($totalStories / $perPage);
        $savedIds = $savedModel->getUserSavedStoryIds($userContext['id']);

        $storyIds = collect($stories)->pluck('id')->toArray();
        $filterService = $this->service(\App\Modules\Stories\Services\StoryFilterService::class);
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        $currentVotes = [];
        if (!empty($storyIds)) {
            $voteModel = $this->container->get(\App\Modules\Votes\Models\Vote::class);
            $currentVotes = $voteModel->getUserClapsForStories($userContext['id'], $storyIds);
        }

        return $this->render('index', array_merge($data, [
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'currentVotes' => $currentVotes,
            'savedIds' => $savedIds,
        ]));
    }

    private function responsesTab(array $data): ViewResponse
    {
        $userContext = $this->getUserContext();

        $commentService = $this->service(\App\Modules\Comments\Services\CommentService::class);
        $comments = $commentService->getUserComments($userContext['id'], 50);

        $commentContext = new \App\Modules\Comments\ViewModels\CommentRenderContext(
            currentUserId: $userContext['id'],
            isAdmin: $userContext['isAdmin'],
            isModerator: $userContext['isModerator'],
        );

        return $this->render('index', array_merge($data, [
            'comments' => $comments,
            'commentContext' => $commentContext,
        ]));
    }

    private function historyTab(array $data): ViewResponse
    {
        $userContext = $this->getUserContext();

        $storyView = $this->service(\App\Modules\Stories\Models\StoryView::class);
        $stories = $storyView->getViewedStories($userContext['id'], 50);

        return $this->render('index', array_merge($data, [
            'stories' => $stories,
        ]));
    }

    private function collectionsTab(array $data): ViewResponse
    {
        $userContext = $this->getUserContext();

        $collectionModel = $this->container->get(\App\Modules\Collections\Models\Collection::class);
        $collections = $collectionModel->getByAuthor($userContext['id']);

        return $this->render('index', array_merge($data, [
            'collections' => $collections,
        ]));
    }
}