<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use W3a\Core\Http\Session;
use W3a\Core\Http\ViewResponse;
use App\BaseController;
use App\Modules\Search\Models\SearchResult;
use App\Modules\Votes\Models\Vote;

/**
 * Контроллер поиска.
 * 
 * Обрабатывает контекстный поиск по историям и комментариям.
 */
class SearchController extends BaseController
{
    /**
     * Обработка контекстного поиска по историям или комментариям (GET /search?q=...)
     */
    public function index(): ViewResponse
    {
        $query  = trim($this->request->getParams('q', ''));
        $sortBy = $this->request->getParams('order', 'relevance');
        $what   = $this->request->getParams('what', 'stories');

        $results = [];
        if (strlen($query) >= 3) {
            $searchModel = $this->service(SearchResult::class);

            if ($what === 'comments') {
                $results = $searchModel->searchComments($query, $sortBy);
            } else {
                $results = $searchModel->searchStories($query, $sortBy);
            }
        } elseif (!empty($query)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Поисковый запрос должен содержать минимум 3 символа.');
        }

        $userContext = $this->getUserContext();

        $userClaps = [];
        if ($userContext['isLoggedIn'] && $what === 'stories' && !empty($results)) {
            $voteModel = $this->container->get(Vote::class);
            $storyIds = array_column($results, 'id');
            $userClaps = $voteModel->getUserClapsForStories($userContext['id'], $storyIds);
        }

        return $this->render('index', [
            'title'   => !empty($query) ? 'Результаты поиска' : 'Поиск по сайту',
            'query'   => $query,
            'sortBy'  => $sortBy,
            'what'    => $what,
            'results' => $results,
            'currentUserId' => $userContext['id'],
            'userClaps' => $userClaps,
        ]);
    }
}
