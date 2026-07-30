<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Support\Logger;
use W3a\Core\Exceptions\NotFoundException;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Tags\Models\Tag;

/**
 * Контроллер Wiki модуля.
 * 
 * Обрабатывает:
 * - Просмотр списка wiki страниц тега
 * - Создание/редактирование/удаление wiki страниц
 * - Восстановление удалённых страниц (для модераторов)
 * - Поиск по wiki
 * - Управление правами доступа
 */
class WikiController extends BaseController
{
    // =========================================================================
    // СПИСОК WIKI СТРАНИЦ ТЕГА
    // =========================================================================

    /**
     * Страница со списком wiki страниц для тега
     */
    public function index(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);

        $wikiService = $this->wikiService();
        $pages = $wikiService->getPagesForTag($tagData['id']);
        $primaryPage = $wikiService->getPrimaryPageForTag($tagData['id']);

        $userContext = $this->getUserContext();
        $canSeeDeleted = $userContext['isAdmin'] || $userContext['isModerator'];
        
        if (!$canSeeDeleted) {
            $pages = array_filter($pages, fn($p) => empty($p['deleted_at']));
            if (!empty($primaryPage) && !empty($primaryPage['deleted_at'])) {
                $primaryPage = null;
            }
        }

        $this->render('index', [
            'title' => 'Wiki: ' . e($tagData['name']),
            'tag' => $tagData,
            'pages' => $pages,
            'primaryPage' => $primaryPage,
            'canSeeDeleted' => $canSeeDeleted,
            'request' => $this->request,
            'breadcrumbs' => $this->renderBreadcrumbs([
                ['url' => '/', 'label' => 'Главная'],
                ['url' => "/t/{$tagslug}", 'label' => "{$tagData['name']}", 'active_pattern' => "/t/{$tagslug}"],
                ['label' => 'Wiki'],
            ])
        ]);
    }

    // =========================================================================
    // ПРОСМОТР WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Просмотр конкретной wiki страницы
     */
    public function show(string $tagslug, string $slug): void
    {
        $tagData = $this->getTagOr404($tagslug);

        $page = $this->wikiService()->getPageBySlug($slug, $tagData['id']);
        
        if (!$page) {
            throw new NotFoundException('Wiki страница не найдена');
        }

        // Увеличиваем счётчик просмотров
        $this->container->get(WikiPage::class)->incrementViewCount((int)$page['id']);

        $userContext = $this->getUserContext();
        
        $canEdit = false;
        $canDelete = false;
        
        if ($userContext['isLoggedIn']) {
            $canEdit = $this->permissionService()->canEditPage($page, $userContext['id']);
            $canDelete = $this->permissionService()->canDeletePage($page, $userContext['id']);
        }

        $this->render('show', [
            'title' => $page['title'] . ' — Wiki',
            'tag' => $tagData,
            'page' => $page,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'request' => $this->request,
            'breadcrumbs' => $this->renderBreadcrumbs([
                ['url' => '/', 'label' => 'Главная'],
                ['url' => "/t/{$tagslug}", 'label' => "{$tagData['name']}", 'active_pattern' => "/t/{$tagslug}"],
                ['url' => "/t/{$tagslug}/wiki", 'label' => 'Wiki', 'active_pattern' => "/t/{$tagslug}/wiki"],
                ['label' => $page['title']],
            ])
        ]);
    }

    // =========================================================================
    // СОЗДАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Форма создания wiki страницы
     */
    public function showCreateForm(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkCreatePermission($tagData);

        $this->render('create', [
            'title' => 'Создать wiki страницу для тега ' . e($tagData['name']),
            'tag' => $tagData,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка создания wiki страницы
     */
    public function create(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkCreatePermission($tagData);

        $slug = trim($this->request->post('slug', ''));
        if ($this->container->get(WikiPage::class)->slugExists($slug, (int)$tagData['id'])) {
            $this->backWithMessage('Страница с таким URL уже существует в этом теге', 'error', "/t/{$tagslug}/wiki/create");
            return;
        }

        $data = [
            'tag_id' => $tagData['id'],
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        $userContext = $this->getUserContext();
        $pageId = $this->wikiService()->createPage($data, $userContext['id']);

        if ($pageId > 0) {
            $page = $this->wikiService()->getById($pageId);
            $this->redirectWithMessage('/t/' . $tagslug . '/wiki/' . $page['slug'], 'Wiki страница успешно создана!', 'success');
            return;
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/create');
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Форма редактирования wiki страницы
     */
    public function showEditForm(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        $this->checkEditPermission($page);

        $old = $this->session()->getFlash('old_input') ?? [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'content' => $page['content'],
            'is_primary' => $page['is_primary']
        ];

        $this->render('edit', [
            'title' => 'Редактировать: ' . e($page['title']),
            'tag' => $tagData,
            'page' => $page,
            'old' => $old,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления wiki страницы
     */
    public function update(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $pageId = (int)$id;
        $page = $this->getPageOr404($pageId, $tagData['id']);
        $this->checkEditPermission($page);

        $data = [
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'edit_summary' => $this->request->getParams('edit_summary', ''),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        if ($this->container->get(WikiPage::class)->slugExists($data['slug'], (int)$tagData['id'], $pageId)) {
            $this->backWithMessage('Страница с таким URL уже существует в этом теге', 'error', "/t/{$tagslug}/wiki/{$pageId}/edit");
            return;
        }

        $userContext = $this->getUserContext();
        
        if ($this->wikiService()->updatePage($pageId, $data, $userContext['id'])) {
            $page = $this->wikiService()->getById($pageId);

            $this->redirectWithMessage('/t/' . $tagslug . '/wiki/' . $page['slug'], 'Wiki страница успешно обновлена!', 'success');
            return;
        }

        $this->session()->set('flash.old_input', $data);
        $this->redirectBack('/t/' . $tagslug . '/wiki/' . $id . '/edit');
    }

    // =========================================================================
    // УДАЛЕНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Удаление wiki страницы (soft delete)
     */
    public function delete(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        $this->checkDeletePermission($page);

        // ✅ Используем getUserContext()
        $userContext = $this->getUserContext();
        
        if ($this->wikiService()->deletePage((int)$id, $userContext['id'])) {
            // ✅ Используем redirectWithMessage()
            $this->redirectWithMessage("/t/{$tagslug}/wiki", 'Wiki страница удалена!', 'success');
            return;
        }

        $this->redirectBack("/t/{$tagslug}/wiki");
    }

    /**
     * Восстановление удалённой wiki страницы (только для модераторов/админов)
     */
    public function restore(string $tagslug, int $id): void
    {
        $userContext = $this->getUserContext();
        
        if (!$userContext['isLoggedIn']) {
            $this->redirectWithMessage('/login', 'Необходима авторизация', 'error');
            return;
        }
        
        if (!$userContext['isAdmin'] && !$userContext['isModerator']) {
            $this->backWithMessage('Недостаточно прав для восстановления', 'error', "/t/{$tagslug}/wiki");
            return;
        }
        
        try {
            $success = $this->wikiService()->restorePage($id, $userContext['id']);
            
            if ($success) {
                $this->redirectWithMessage("/t/{$tagslug}/wiki", 'Wiki страница успешно восстановлена', 'success');
            } else {
                $this->redirectWithMessage("/t/{$tagslug}/wiki", 'Не удалось восстановить страницу', 'error');
            }
        } catch (\Throwable $e) {
            $this->container->get(Logger::class)->error(
                "[WIKI] Error in restore controller: " . $e->getMessage()
            );
            $this->redirectWithMessage("/t/{$tagslug}/wiki", 'Произошла ошибка при восстановлении страницы', 'error');
        }
    }

    // =========================================================================
    // ПОИСК ПО WIKИ
    // =========================================================================

    /**
     * Поиск по wiki страницам тега
     */
    public function search(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $query = trim($this->request->getParams('q', ''));

        if (empty($query)) {
            $this->redirect('/t/' . $tagslug . '/wiki');
            return;
        }

        $results = $this->wikiService()->searchInTag($tagData['id'], $query);

        $this->render('search', [
            'title' => 'Поиск в wiki: ' . e($query),
            'tag' => $tagData,
            'query' => $query,
            'results' => $results
        ]);
    }

    // =========================================================================
    // УПРАВЛЕНИЕ ПРАВАМИ
    // =========================================================================

    /**
     * Страница управления правами wiki для тега
     */
    public function permissions(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может управлять правами');

        $editors = $this->permissionService()->getTagEditors($tagData['id']);

        $this->render('permissions', [
            'title' => 'Управление правами wiki: ' . e($tagData['name']),
            'tag' => $tagData,
            'editors' => $editors
        ]);
    }

    /**
     * Выдача прав пользователю на wiki
     */
    public function grantPermission(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может давать права');

        $targetUsername = trim($this->request->getParams('username', ''));
        $canEdit = is_numeric($this->request->getParams('can_edit'));
        $canDelete = is_numeric($this->request->getParams('can_delete'));

        if (empty($targetUsername)) {
            $this->backWithMessage('Укажите имя пользователя', 'error', "/t/{$tagslug}/wiki/permissions");
            return;
        }

        $userContext = $this->getUserContext();
        
        if ($this->permissionService()->grantPermission(
            $tagData['id'],
            $targetUsername,
            $userContext['id'],
            $canEdit,
            $canDelete
        )) {
            $this->redirectWithMessage(
                "/t/{$tagslug}/wiki/permissions",
                'Права успешно выданы пользователю ' . e($targetUsername),
                'success'
            );
            return;
        }

        $this->redirectBack("/t/{$tagslug}/wiki/permissions");
    }

    /**
     * Отзыв прав у пользователя
     */
    public function revokePermission(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может отзывать права');

        $targetUserId = (int)$this->request->getParams('user_id', 0);

        if (!$targetUserId) {
            $this->backWithMessage('Не указан пользователь', 'error', "/t/{$tagslug}/wiki/permissions");
            return;
        }

        $userContext = $this->getUserContext();
        
        if ($this->permissionService()->revokePermission($tagData['id'], $targetUserId, $userContext['id'])) {
            $this->redirectWithMessage(
                "/t/{$tagslug}/wiki/permissions",
                'Права успешно отозваны',
                'success'
            );
            return;
        }

        $this->redirectBack("/t/{$tagslug}/wiki/permissions");
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * Получить WikiService из контейнера
     */
    private function wikiService(): WikiService
    {
        return $this->service(WikiService::class);
    }

    /**
     * Получить WikiPermissionService из контейнера
     */
    private function permissionService(): WikiPermissionService
    {
        return $this->service(WikiPermissionService::class);
    }

    /**
     * Получить тег или выбросить 404
     */
    private function getTagOr404(string $tagslug): array
    {
        $tagData = $this->container->get(Tag::class)->getBySlug($tagslug);
        
        if (!$tagData) {
            throw new NotFoundException('Тег не найден');
        }
        
        return $tagData;
    }

    /**
     * Получить wiki страницу или выбросить 404
     */
    private function getPageOr404(int $pageId, int $tagId): array
    {
        $page = $this->wikiService()->getById($pageId);
        
        if (!$page || $page['tag_id'] != $tagId) {
            throw new NotFoundException('Wiki страница не найдена');
        }
        
        return $page;
    }

    /**
     * Проверить права на создание wiki.
     */
    private function checkCreatePermission(array $tagData): void
    {
        $userContext = $this->getUserContext();
        
        if (!$this->permissionService()->canCreateWikiForTag($tagData['id'], $userContext['id'])) {
            $this->backWithMessage(
                'У вас нет прав создавать wiki для этого тега',
                'error',
                '/t/' . $tagData['slug'] . '/wiki'
            );
        }
    }

    /**
     * Проверить права на редактирование wiki.
     */
    private function checkEditPermission(array $page): void
    {
        $userContext = $this->getUserContext();
        
        if (!$this->permissionService()->canEditPage($page, $userContext['id'])) {
            $this->backWithMessage(
                'У вас нет прав редактировать эту страницу',
                'error',
                '/t/' . $page['tag_slug'] . '/wiki/' . $page['slug']
            );
        }
    }

    /**
     * Проверить права на удаление wiki.
     */
    private function checkDeletePermission(array $page): void
    {
        $userContext = $this->getUserContext();
        
        if (!$this->permissionService()->canDeletePage($page, $userContext['id'])) {
            $this->backWithMessage(
                'У вас нет прав удалять эту страницу',
                'error',
                '/t/' . $page['tag_slug'] . '/wiki'
            );
        }
    }

    /**
     * Проверить что пользователь владелец тега или админ.
     */
    private function checkTagOwnerOrAdmin(array $tagData, string $errorMessage): void
    {
        $userContext = $this->getUserContext();
        
        if ($tagData['user_id'] != $userContext['id'] && !$userContext['isAdmin']) {
            $this->backWithMessage(
                $errorMessage,
                'error',
                '/t/' . $tagData['slug'] . '/wiki'
            );
        }
    }
}