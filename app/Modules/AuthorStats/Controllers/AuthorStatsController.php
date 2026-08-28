<?php

declare(strict_types=1);

namespace App\Modules\AuthorStats\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;
use App\Modules\AuthorStats\Models\AuthorStatsModel;

class AuthorStatsController extends BaseController
{
    public function index(): ViewResponse
    {
        $userContext = $this->getUserContext();
        $userId = $userContext['id'];

        if ($userId <= 0) {
            return $this->redirect('/login');
        }

        $model = $this->service(AuthorStatsModel::class);

        $totalViews = $model->getTotalViews($userId);
        $uniqueReaders = $model->getUniqueReaders($userId);
        $avgReadTime = $model->getAvgReadTime($userId);
        $totalClaps = $model->getTotalClapsReceived($userId);
        $stories = $model->getStoriesStats($userId);
        $recentReaders = $model->getRecentReaders($userId);

        return $this->render('index', [
            'title'         => 'Моя статистика',
            'totalViews'    => $totalViews,
            'uniqueReaders' => $uniqueReaders,
            'avgReadTime'   => round($avgReadTime),
            'totalClaps'    => $totalClaps,
            'stories'       => $stories,
            'recentReaders' => $recentReaders,
        ]);
    }
}