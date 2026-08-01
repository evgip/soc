<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\Logger;
use W3a\Core\Support\MessageBag;
use W3a\Core\Exceptions\NotFoundException;

use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Tags\Models\Tag;

/**
 * Контроллер Wiki модуля.
 */
class WikiController extends BaseController
{
    // =========================================================================
    // СПИСОК WIKI СТРАНИЦ ТЕГА
    // =========================================================================

    public function index(string $tagslug): ViewResponse
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

        return $this->render('index', [
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

    public function show(string $tagslug, string $slug): ViewResponse
    {
        $tagData = $this->getTagOr404($tagslug);

        $page = $this->wikiService()->getPageBySlug($slug, $tagData['id']);
        
        if (!$page) {
            throw new NotFoundException('Wiki страница не найдена');
        }

        $this->container->get(WikiPage::class)->incrementViewCount((int)$page['id']);

        $userContext = $this->getUserContext();
        $canEdit = false;
        $canDelete = false;
        
        if ($userContext['isLoggedIn']) {
            $canEdit = $this->permissionService()->canEditPage($page, $userContext['id']);
            $canDelete = $this->permissionService()->canDeletePage($page, $userContext['id']);
        }

        return $this->render('show', [
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

    public function showCreateForm(string $tagslug): Response
    {
        $tagData = $this->getTagOr404($tagslug);
        if ($redirect = $this->checkCreatePermission($tagData)) {
            return $redirect;
        }

        return $this->render('create', [
            'title' => 'Создать wiki страницу для тега ' . e($tagData['name']),
            'tag' => $tagData,
            'request' => $this->request
        ]);
    }

    public function create(string $tagslug): RedirectResponse
    {
        $tagData = $this->getTagOr404($tagslug);
        if ($redirect = $this->checkCreatePermission($tagData)) {
            return $redirect;
        }

        $slug = trim($this->request->post('slug', ''));
        if ($this->container->get(WikiPage::class)->slugExists($slug, (int)$tagData['id'])) {
            MessageBag::flashMessage('error', 'Страница с таким URL уже существует в этом теге');
            return $this->redirectBack("/t/{$tagslug}/wiki/create");
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
            MessageBag::flashMessage('success', 'Wiki страница успешно создана!');
            return $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        return $this->redirectBack('/t/' . $tagslug . '/wiki/create');
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    public function showEditForm(string $tagslug, string $id): Response
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        
        if ($redirect = $this->checkEditPermission($page)) {
            return $redirect;
        }

        // Примечание: old_input больше не нужно передавать вручную. 
        // MessageBag автоматически сделает его доступным в шаблоне через $errors->getOld()

        return $this->render('edit', [
            'title' => 'Редактировать: ' . e($page['title']),
            'tag' => $tagData,
            'page' => $page,
            'request' => $this->request
        ]);
    }

    public function update(string $tagslug, string $id): RedirectResponse
    {
        $tagData = $this->getTagOr404($tagslug);
        $pageId = (int)$id;
        $page = $this->getPageOr404($pageId, $tagData['id']);
        
        if ($redirect = $this->checkEditPermission($page)) {
            return $redirect;
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'edit_summary' => $this->request->getParams('edit_summary', ''),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        if ($this->container->get(WikiPage::class)->slugExists($data['slug'], (int)$tagData['id'], $pageId)) {
            MessageBag::flashMessage('error', 'Страница с таким URL уже существует в этом теге');
            MessageBag::flashErrors([], $data); // Сохраняем введенные данные
            return $this->redirectBack("/t/{$tagslug}/wiki/{$pageId}/edit");
        }

        $userContext = $this->getUserContext();
        
        if ($this->wikiService()->updatePage($pageId, $data, $userContext['id'])) {
            $page = $this->wikiService()->getById($pageId);
            MessageBag::flashMessage('success', 'Wiki страница успешно обновлена!');
            return $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        MessageBag::flashErrors([], $data);
        return $this->redirectBack('/t/' . $tagslug . '/wiki/' . $id . '/edit');
    }

    // =========================================================================
    // УДАЛЕНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    public function delete(string $tagslug, string $id): RedirectResponse
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        
        if ($redirect = $this->checkDeletePermission($page)) {
            return $redirect;
        }

        $userContext = $this->getUserContext();
        
        if ($this->wikiService()->deletePage((int)$id, $userContext['id'])) {
            MessageBag::flashMessage('success', 'Wiki страница удалена!');
            return $this->redirect("/t/{$tagslug}/wiki");
        }

        return $this->redirectBack("/t/{$tagslug}/wiki");
    }

    public function restore(string $tagslug, int $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        
        if (!$userContext['isLoggedIn']) {
            MessageBag::flashMessage('error', 'Необходима авторизация');
            return $this->redirect('/login');
        }
        
        if (!$userContext['isAdmin'] && !$userContext['isModerator']) {
            MessageBag::flashMessage('error', 'Недостаточно прав для восстановления');
            return $this->redirectBack("/t/{$tagslug}/wiki");
        }
        
        try {
            $success = $this->wikiService()->restorePage($id, $userContext['id']);
            
            if ($success) {
                MessageBag::flashMessage('success', 'Wiki страница успешно восстановлена');
            } else {
                MessageBag::flashMessage('error', 'Не удалось восстановить страницу');
            }
            return $this->redirect("/t/{$tagslug}/wiki");
            
        } catch (\Throwable $e) {
            $this->container->get(Logger::class)->error("[WIKI] Error in restore controller: " . $e->getMessage());
            MessageBag::flashMessage('error', 'Произошла ошибка при восстановлении страницы');
            return $this->redirect("/t/{$tagslug}/wiki");
        }
    }

    // =========================================================================
    // ПОИСК ПО WIKИ
    // =========================================================================

    public function search(string $tagslug): Response
    {
        $tagData = $this->getTagOr404($tagslug);
        $query = trim($this->request->getParams('q', ''));

        if (empty($query)) {
            return $this->redirect('/t/' . $tagslug . '/wiki');
        }

        $results = $this->wikiService()->searchInTag($tagData['id'], $query);

        return $this->render('search', [
            'title' => 'Поиск в wiki: ' . e($query),
            'tag' => $tagData,
            'query' => $query,
            'results' => $results
        ]);
    }

    // =========================================================================
    // УПРАВЛЕНИЕ ПРАВАМИ
    // =========================================================================

    public function permissions(string $tagslug): Response
    {
        $tagData = $this->getTagOr404($tagslug);
        if ($redirect = $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может управлять правами')) {
            return $redirect;
        }

        $editors = $this->permissionService()->getTagEditors($tagData['id']);

        return $this->render('permissions', [
            'title' => 'Управление правами wiki: ' . e($tagData['name']),
            'tag' => $tagData,
            'editors' => $editors
        ]);
    }

    public function grantPermission(string $tagslug): RedirectResponse
    {
        $tagData = $this->getTagOr404($tagslug);
        if ($redirect = $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может давать права')) {
            return $redirect;
        }

        $targetUsername = trim($this->request->getParams('username', ''));
        $canEdit = is_numeric($this->request->getParams('can_edit'));
        $canDelete = is_numeric($this->request->getParams('can_delete'));

        if (empty($targetUsername)) {
            MessageBag::flashMessage('error', 'Укажите имя пользователя');
            return $this->redirectBack("/t/{$tagslug}/wiki/permissions");
        }

        $userContext = $this->getUserContext();
        
        if ($this->permissionService()->grantPermission($tagData['id'], $targetUsername, $userContext['id'], $canEdit, $canDelete)) {
            MessageBag::flashMessage('success', 'Права успешно выданы пользователю ' . e($targetUsername));
            return $this->redirect("/t/{$tagslug}/wiki/permissions");
        }

        return $this->redirectBack("/t/{$tagslug}/wiki/permissions");
    }

    public function revokePermission(string $tagslug): RedirectResponse
    {
        $tagData = $this->getTagOr404($tagslug);
        if ($redirect = $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может отзывать права')) {
            return $redirect;
        }

        $targetUserId = (int)$this->request->getParams('user_id', 0);

        if (!$targetUserId) {
            MessageBag::flashMessage('error', 'Не указан пользователь');
            return $this->redirectBack("/t/{$tagslug}/wiki/permissions");
        }

        $userContext = $this->getUserContext();
        
        if ($this->permissionService()->revokePermission($tagData['id'], $targetUserId, $userContext['id'])) {
            MessageBag::flashMessage('success', 'Права успешно отозваны');
            return $this->redirect("/t/{$tagslug}/wiki/permissions");
        }

        return $this->redirectBack("/t/{$tagslug}/wiki/permissions");
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function wikiService(): WikiService
    {
        return $this->service(WikiService::class);
    }

    private function permissionService(): WikiPermissionService
    {
        return $this->service(WikiPermissionService::class);
    }

    private function getTagOr404(string $tagslug): array
    {
        $tagData = $this->container->get(Tag::class)->getBySlug($tagslug);
        
        if (!$tagData) {
            throw new NotFoundException('Тег не найден');
        }
        
        return $tagData;
    }

    private function getPageOr404(int $pageId, int $tagId): array
    {
        $page = $this->wikiService()->getById($pageId);
        
        if (!$page || $page['tag_id'] != $tagId) {
            throw new NotFoundException('Wiki страница не найдена');
        }
        
        return $page;
    }

    /**
     * Возвращает RedirectResponse, если прав нет, иначе null.
     */
    private function checkCreatePermission(array $tagData): ?RedirectResponse
    {
        $userContext = $this->getUserContext();
        if (!$this->permissionService()->canCreateWikiForTag($tagData['id'], $userContext['id'])) {
            MessageBag::flashMessage('error', 'У вас нет прав создавать wiki для этого тега');
            return $this->redirectBack('/t/' . $tagData['slug'] . '/wiki');
        }
        return null;
    }

    private function checkEditPermission(array $page): ?RedirectResponse
    {
        $userContext = $this->getUserContext();
        if (!$this->permissionService()->canEditPage($page, $userContext['id'])) {
            MessageBag::flashMessage('error', 'У вас нет прав редактировать эту страницу');
            return $this->redirectBack('/t/' . $page['tag_slug'] . '/wiki/' . $page['slug']);
        }
        return null;
    }

    private function checkDeletePermission(array $page): ?RedirectResponse
    {
        $userContext = $this->getUserContext();
        if (!$this->permissionService()->canDeletePage($page, $userContext['id'])) {
            MessageBag::flashMessage('error', 'У вас нет прав удалять эту страницу');
            return $this->redirectBack('/t/' . $page['tag_slug'] . '/wiki');
        }
        return null;
    }

    private function checkTagOwnerOrAdmin(array $tagData, string $errorMessage): ?RedirectResponse
    {
        $userContext = $this->getUserContext();
        if ($tagData['user_id'] != $userContext['id'] && !$userContext['isAdmin']) {
            MessageBag::flashMessage('error', $errorMessage);
            return $this->redirectBack('/t/' . $tagData['slug'] . '/wiki');
        }
        return null;
    }
}