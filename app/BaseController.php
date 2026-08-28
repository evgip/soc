<?php

declare(strict_types=1);

namespace App;

use W3a\Core\Http\Controller as CoreController;
use W3a\Core\Http\Session;
use W3a\Core\View\OpenGraph;
use W3a\Core\Auth\Auth;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Flags\Models\Flag;
use App\Modules\Suggestions\Models\Suggestion;

/**
 * Базовый контроллер приложения.
 * Наследует всю общую функциональность из W3a\Core\Http\Controller,
 * но добавляет специфичную для этого проекта логику.
 */
abstract class BaseController extends CoreController
{
    /** @var array|null Кеш данных для view */
    private ?array $appViewDataCache = null;

    /** @var array|null Кеш контекста пользователя с ролями */
    private ?array $userContextCache = null;

    /**
     * Переопределяем метод ядра, чтобы добавить информацию о ролях.
     */
    protected function getUserContext(): array
    {
        if ($this->userContextCache !== null) {
            return $this->userContextCache;
        }

        $isLoggedIn = Auth::check();
        $userId = $isLoggedIn ? (int)Auth::id() : 0;
        $isAdmin = Auth::isAdmin();
        $isModerator = Auth::isModerator();

        $this->userContextCache = [
            'id' => $userId,
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'isModerator' => $isModerator,
            'isAuthor' => fn(int $authorId): bool => $isLoggedIn && $userId === $authorId,
        ];

        return $this->userContextCache;
    }

    /**
     * Реализация метода из ядра для предоставления специфичных данных шаблону.
     */
    protected function getAppViewData(): array
    {
        if ($this->appViewDataCache !== null) {
            return $this->appViewDataCache;
        }

        $data = [
            'currentUser' => [
                'id' => null, 'name' => null, 'role' => null, 'avatar' => null,
                'isLoggedIn' => false, 'isAdmin' => false, 'isModerator' => false,
            ],
            'unreadNotificationsCount' => 0,
            'pendingFlagsCount' => 0,
            'activeSuggestionsCount' => 0,
        ];

        try {
            $session = $this->container->get(Session::class);
            $userId = $session->get('user_id');

            if (!$userId) {
                $this->appViewDataCache = $data;
                return $data;
            }

            $data['currentUser'] = [
                'id' => $userId,
                'name' => $session->get('user_name'),
                'role' => $session->get('user_role'),
                'avatar' => $session->get('user_avatar'),
                'isLoggedIn' => true,
                'isAdmin' => ($session->get('user_role') === 'admin'),
                'isModerator' => in_array($session->get('user_role'), ['admin', 'moderator']),
            ];

            $data['unreadNotificationsCount'] = $this->getUnreadNotificationsCount((int)$userId);
			
            if ($data['currentUser']['isModerator']) {
                $data['pendingFlagsCount'] = $this->getPendingFlagsCount();
                $data['activeSuggestionsCount'] = $this->getActiveSuggestionsCount();
            }
        } catch (\Throwable $e) {
            $this->logError($e, 'BaseController.getAppViewData');
        }

        $this->appViewDataCache = $data;
        return $data;
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ СЧЁТЧИКОВ
    // =========================================================================

    private function getUnreadNotificationsCount(int $userId): int
    {
        try {
            $notifModel = $this->container->get(Notification::class);
            $muteService = $this->container->get(MuteService::class);
            $mutedUserIds = $muteService->getMutedUserIds($userId);
            return $notifModel->getUnreadCount($userId, $mutedUserIds);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getPendingFlagsCount(): int
    {
        try {
            $flagModel = $this->container->get(Flag::class);
            return $flagModel->getPendingCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getActiveSuggestionsCount(): int
    {
        try {
            $suggestionModel = $this->container->get(Suggestion::class);
            return $suggestionModel->countAllActive();
        } catch (\Throwable $e) {
            return 0;
        }
    }

// =========================================================================
    // СПЕЦИФИЧНАЯ БИЗНЕС-ЛОГИКА И ХЕЛПЕРЫ
    // =========================================================================

    /**
     * Установить Open Graph мета-теги для страницы.
     */
    protected function setOpenGraph(array $data): void
    {
        if (!isset($data['url'])) {
            $host = $this->request->header('HTTP_HOST', 'localhost');
            $uri = $this->request->getUri();
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $data['url'] = $protocol . $host . $uri;
        }

        OpenGraph::set($data);
    }

    /**
     * Отрендерить хлебные крошки (breadcrumbs).
     */
    protected function renderBreadcrumbs(array $items): string
    {
        $html = '<nav aria-label="Breadcrumb"><ol>';
        foreach ($items as $item) {
            $label = $item['label'] ?? $item['title'] ?? '';
            if (isset($item['url'])) {
                $html .= '<li><a href="' . htmlspecialchars((string)$item['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</a></li>';
            } else {
                $html .= '<li aria-current="page">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }
        $html .= '</ol></nav>';
        return $html;
    }
}