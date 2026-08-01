<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use W3a\Core\Support\Audit;
use W3a\Core\Support\MessageBag;
use W3a\Core\Http\Router;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Http\ViewResponse;

use App\BaseController;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminTagService;
use App\Modules\Admin\Services\AdminCategoryService;
use App\Modules\Admin\Services\AdminAuditService;
use App\Modules\Admin\Services\AdminToolsService;
use App\Modules\Admin\Services\AdminFirewallService;
use App\Modules\Admin\Services\AdminInvitationService;

use App\Modules\Admin\Exceptions\AdminValidationException;
use App\Modules\Admin\Exceptions\AdminUserException;

use App\Modules\Wiki\Models\WikiPage;

/**
 * Административный контроллер.
 */
class AdminController extends BaseController
{
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

    private function wikiPage(): WikiPage
    {
        return $this->container->get(WikiPage::class);
    }

    private function router(): Router
    {
        return $this->container->get(Router::class);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(): ViewResponse
    {
        $users = $this->service(AdminUserService::class)->getAllUsers();

        return $this->render('dashboard', [
            'title' => 'Панель управления',
            'totalUsers' => count($users),
            'totalAdmins' => count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'))
        ]);
    }

    // =========================================================================
    // ПОЛЬЗОВАТЕЛИ
    // =========================================================================

    public function users(): ViewResponse
    {
        return $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $this->service(AdminUserService::class)->getAllUsers()
        ]);
    }

    public function usersIndex(): ViewResponse
    {
        return $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $this->service(AdminUserService::class)->getAdminUsersList(100),
            'request' => $this->request
        ]);
    }

    public function editUser(string $id): Response
    {
        $user = $this->service(AdminUserService::class)->findUser((int)$id);

        if (!$user) {
            return $this->redirectBack('/admin/users');
        }

        return $this->render('user_edit_panel', [
            'title' => 'Модерация профиля: ' . e($user['username']),
            'userItem' => $user,
            'request' => $this->request
        ]);
    }

    public function updateUser(string $id): RedirectResponse
    {
        $this->service(AdminUserService::class)->updateUserProfile((int)$id, [
            'email' => $this->request->getParams('email'),
            'role' => $this->request->getParams('role'),
            'bio' => $this->request->getParams('bio'),
        ]);

        return $this->redirectWithMessage('/admin/users', 'Данные профиля пользователя успешно изменены администратором.', 'success');
    }

    public function archiveUser(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        try {
            $this->service(AdminUserService::class)->archiveUser((int)$id, $userContext['id']);
            return $this->redirectWithMessage('/admin/users', 'Пользователь успешно отправлен в архив.', 'success');
        } catch (AdminUserException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage('/admin/users', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.archiveUser');
            MessageBag::flashMessage('error', 'Произошла ошибка при архивации.');
            return $this->redirectWithMessage('/admin/users', 'Произошла ошибка при архивации.', 'error');
        }
    }

    public function restoreUser(string $id): RedirectResponse
    {
        $this->service(AdminUserService::class)->restoreUser((int)$id);
        return $this->redirectWithMessage('/admin/users', 'Аккаунт пользователя успешно восстановлен из архива.', 'success');
    }

    public function toggleUserStatus(string $id): RedirectResponse
    {
        $userContext = $this->getUserContext();
        try {
            $result = $this->service(AdminUserService::class)->toggleUserStatus((int)$id, $userContext['id']);
            
            if ($result === 0) {
                return $this->redirectWithMessage('/admin/users', 'Пользователь успешно заблокирован.', 'success');
            } else {
                return $this->redirectWithMessage('/admin/users', 'Доступ для пользователя успешно восстановлен.', 'success');
            }
        } catch (AdminUserException | AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage('/admin/users', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.toggleUserStatus');
            MessageBag::flashMessage('error', 'Произошла ошибка.');
            return $this->redirectWithMessage('/admin/users', 'Произошла ошибка.', 'error');
        }
    }

    public function deleteUserAvatar(string $id): RedirectResponse
    {
        $userId = (int)$id;

        if ($this->service(AdminUserService::class)->deleteUserAvatar($userId)) {
            return $this->redirectWithMessage("/admin/users/{$userId}/edit", 'Аватар пользователя успешно удален.', 'success');
        }

        return $this->redirect("/admin/users/{$userId}/edit");
    }

    // =========================================================================
    // ТЕГИ
    // =========================================================================

    public function tagsIndex(): ViewResponse
    {
        return $this->render('tags_list', [
            'title' => 'Управление тегами',
            'tags' => $this->service(AdminTagService::class)->getAllTags()
        ]);
    }

    public function showTagCreateForm(): ViewResponse
    {
        return $this->render('tag_create', [
            'title' => 'Создание нового тега',
            'request' => $this->request
        ]);
    }

    public function createTag(): RedirectResponse
    {
        try {
            $this->service(AdminTagService::class)->createTag([
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'is_media' => $this->request->post('is_media') !== null ? 1 : 0,
                'category_id' => $this->request->getParams('category_id'),
            ]);

            return $this->redirectWithMessage('/admin/tags', 'Тег успешно добавлен.', 'success');
        } catch (AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage('/admin/tags/create', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.createTag');
            MessageBag::flashMessage('error', 'Произошла ошибка при создании тега.');
            return $this->redirectWithMessage('/admin/tags/create', 'Произошла ошибка при создании тега.', 'error');
        }
    }

    public function showTagEditForm(string $id): Response
    {
        $tag = $this->service(AdminTagService::class)->getTagById((int)$id);

        if (!$tag) {
            return $this->redirectBack('/admin/tags');
        }

        return $this->render('tag_edit', [
            'title' => 'Редактирование тега #' . e($tag['slug']),
            'tagItem' => $tag,
            'request' => $this->request
        ]);
    }

    public function updateTag(string $id): RedirectResponse
    {
        $tagId = (int)$id;
        try {
            $this->service(AdminTagService::class)->updateTag($tagId, [
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'is_media' => $this->request->post('is_media') !== null ? 1 : 0,
                'category_id' => $this->request->getParams('category_id'),
                'hotness_mod' => $this->request->getParams('hotness_mod'),
            ]);

            return $this->redirectWithMessage('/admin/tags', 'Параметры тега сохранены.', 'success');
        } catch (AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage("/admin/tags/{$tagId}/edit", $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.updateTag');
            MessageBag::flashMessage('error', 'Произошла ошибка при обновлении.');
            return $this->redirectWithMessage("/admin/tags/{$tagId}/edit", 'Произошла ошибка при обновлении.', 'error');
        }
    }

    public function deleteTag(string $id): RedirectResponse
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->softDeleteTag($tagId);

        if ($success) {
            return $this->redirectWithMessage('/admin/tags', 'Тег успешно удален (перемещен в архив).', 'success');
        }

        MessageBag::flashMessage('error', 'Не удалось удалить тег.');
        return $this->redirectWithMessage('/admin/tags', 'Не удалось удалить тег.', 'error');
    }

    public function restoreTag(string $id): RedirectResponse
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->restoreTag($tagId);

        if ($success) {
            return $this->redirectWithMessage('/admin/tags', 'Тег успешно восстановлен.', 'success');
        }

        MessageBag::flashMessage('error', 'Не удалось восстановить тег.');
        return $this->redirectWithMessage('/admin/tags', 'Не удалось восстановить тег.', 'error');
    }

    // =========================================================================
    // КАТЕГОРИИ
    // =========================================================================

    public function categoriesIndex(): ViewResponse
    {
        return $this->render('categories_list', [
            'title' => 'Управление категориями тегов',
            'categories' => $this->service(AdminCategoryService::class)->getCategoriesList()
        ]);
    }

    public function showCategoryCreateForm(): ViewResponse
    {
        return $this->render('category_create', [
            'title' => 'Создание новой категории',
            'request' => $this->request
        ]);
    }

    public function createCategory(): RedirectResponse
    {
        try {
            $this->service(AdminCategoryService::class)->createCategory([
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'sort_order' => $this->request->getParams('sort_order'),
            ]);

            return $this->redirectWithMessage('/admin/categories', 'Категория успешно создана.', 'success');
        } catch (AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage('/admin/categories/create', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.createCategory');
            MessageBag::flashMessage('error', 'Произошла ошибка.');
            return $this->redirectWithMessage('/admin/categories/create', 'Произошла ошибка.', 'error');
        }
    }

    public function showCategoryEditForm(string $id): Response
    {
        $category = $this->service(AdminCategoryService::class)->getCategoryById((int)$id);

        if (!$category) {
            MessageBag::flashMessage('error', 'Категория не найдена.');
            return $this->redirectBack('/admin/categories');
        }

        return $this->render('category_edit', [
            'title' => 'Редактирование категории: ' . e($category['name']),
            'categoryItem' => $category,
            'request' => $this->request
        ]);
    }

    public function updateCategory(string $id): RedirectResponse
    {
        $categoryId = (int)$id;
        try {
            $this->service(AdminCategoryService::class)->updateCategory($categoryId, [
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'sort_order' => $this->request->getParams('sort_order'),
            ]);

            return $this->redirectWithMessage('/admin/categories', 'Категория успешно обновлена.', 'success');
        } catch (AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage("/admin/categories/{$categoryId}/edit", $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.updateCategory');
            MessageBag::flashMessage('error', 'Произошла ошибка.');
            return $this->redirectWithMessage("/admin/categories/{$categoryId}/edit", 'Произошла ошибка.', 'error');
        }
    }

    public function deleteCategory(string $id): RedirectResponse
    {
        try {
            $this->service(AdminCategoryService::class)->deleteCategory((int)$id);
            return $this->redirectWithMessage('/admin/categories', 'Категория успешно удалена.', 'success');
        } catch (AdminValidationException $e) {
            MessageBag::flashMessage('error', $e->getMessage());
            return $this->redirectWithMessage('/admin/categories', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.deleteCategory');
            MessageBag::flashMessage('error', 'Произошла ошибка при удалении.');
            return $this->redirectWithMessage('/admin/categories', 'Произошла ошибка при удалении.', 'error');
        }
    }

    // =========================================================================
    // WIKI СТРАНИЦЫ
    // =========================================================================

    public function wikiIndex(): ViewResponse
    {
        $wikiPage = $this->wikiPage();

        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $pages = $wikiPage->getAllPagesWithTags($perPage, $offset);
        $totalPages = $wikiPage->getTotalPagesCount();
        $deletedPages = $wikiPage->getDeletedPagesCount();

        return $this->render('wiki_list', [
            'title' => 'Управление Wiki страницами',
            'pages' => $pages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'deletedPages' => $deletedPages,
            'totalPagesCount' => ceil($totalPages / $perPage),
        ]);
    }

    public function deleteWikiPage(string $id): RedirectResponse
    {
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);

        if (!$page) {
            MessageBag::flashMessage('error', 'Wiki страница не найдена');
            return $this->redirectBack('/admin/wiki');
        }

        if ($wikiPage->softDelete((int)$id)) {
            $userContext = $this->getUserContext();

            $this->audit()->log('admin.wiki.deleted', 'Wiki страница удалена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => $userContext['id'],
            ]);

            return $this->redirectWithMessage('/admin/wiki', "Wiki страница «{$page['title']}» удалена", 'success');
        }

        MessageBag::flashMessage('error', 'Ошибка при удалении wiki страницы');
        return $this->redirectWithMessage('/admin/wiki', 'Ошибка при удалении wiki страницы', 'error');
    }

    public function restoreWikiPage(string $id): RedirectResponse
    {
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);

        if (!$page) {
            MessageBag::flashMessage('error', 'Wiki страница не найдена');
            return $this->redirectBack('/admin/wiki');
        }

        if ($wikiPage->restore((int)$id)) {
            $userContext = $this->getUserContext();

            $this->audit()->log('admin.wiki.restored', 'Wiki страница восстановлена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => $userContext['id'],
            ]);

            return $this->redirectWithMessage('/admin/wiki', "Wiki страница «{$page['title']}» восстановлена", 'success');
        }

        MessageBag::flashMessage('error', 'Ошибка при восстановлении wiki страницы');
        return $this->redirectWithMessage('/admin/wiki', 'Ошибка при восстановлении wiki страницы', 'error');
    }

    // =========================================================================
    // АУДИТ
    // =========================================================================

    public function auditLogs(): ViewResponse
    {
        $filterUserIdRaw = $this->request->query('filter_user_id');
        $filterUserId = ($filterUserIdRaw !== null && $filterUserIdRaw !== '') ? (int)$filterUserIdRaw : null;

        $filterActionRaw = $this->request->query('filter_action');
        $filterAction = ($filterActionRaw !== null && $filterActionRaw !== '') ? trim($filterActionRaw) : null;

        $filterCategoryRaw = $this->request->query('category');
        $filterCategory = ($filterCategoryRaw !== null && $filterCategoryRaw !== '') ? trim($filterCategoryRaw) : null;

        $searchQueryRaw = $this->request->query('search');
        $searchQuery = ($searchQueryRaw !== null && $searchQueryRaw !== '') ? trim($searchQueryRaw) : null;

        $currentPage = max(1, (int)$this->request->query('page', 1));
        $perPage = 25;
        $offset = ($currentPage - 1) * $perPage;

        $auditService = $this->service(AdminAuditService::class);

        $logs = $auditService->getFilteredLogs($perPage, $offset, $filterUserId, $filterAction, $searchQuery, $filterCategory);
        $totalLogs = $auditService->getFilteredCount($filterUserId, $filterAction, $searchQuery, $filterCategory);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));

        return $this->render('audit_list', [
            'title' => 'Журнал аудита системы',
            'logs' => $logs,
            'uniqueActions' => $auditService->getUniqueActions(),
            'uniqueCategories' => $auditService->getUniqueCategories(),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'currentFilters' => [
                'user_id' => $filterUserId,
                'action' => $filterAction,
                'search' => $searchQuery,
                'category' => $filterCategory
            ],
            'categoryLabels' => [
                'general' => 'Обычные',
                'moderation' => 'Модерация',
                'admin' => 'Администрирование',
                'security' => 'Безопасность',
                'system' => 'Системные',
            ]
        ]);
    }

    public function getSecurityAlertsApi(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'alerts' => $this->service(AdminAuditService::class)->getRecentSecurityAlerts(),
            'timestamp' => time()
        ]);
    }

    // =========================================================================
    // FIREWALL
    // =========================================================================

    public function firewallIndex(): ViewResponse
    {
        return $this->render('firewall', [
            'title' => 'Сетевой экран (Firewall)',
            'bannedIps' => $this->service(AdminFirewallService::class)->getBannedIps(),
            'request' => $this->request
        ]);
    }

    public function banIp(): RedirectResponse
    {
        $ip = trim($this->request->getParams('ip_address'));
        $reason = trim($this->request->getParams('reason')) ?: 'Нарушение правил сообщества';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            MessageBag::flashMessage('error', 'Указан некорректный IP-адрес.');
            return $this->redirectWithMessage('/admin/firewall', 'Указан некорректный IP-адрес.', 'error');
        }

        if ($this->service(AdminFirewallService::class)->banIp($ip, $reason)) {
            return $this->redirectWithMessage('/admin/firewall', "IP-адрес {$ip} успешно внесен в черный список.", 'success');
        }

        MessageBag::flashMessage('error', 'Этот IP-адрес уже заблокирован.');
        return $this->redirectWithMessage('/admin/firewall', 'Этот IP-адрес уже заблокирован.', 'error');
    }

    public function unbanIp(string $id): RedirectResponse
    {
        $ip = $this->service(AdminFirewallService::class)->unbanIp((int)$id);

        if ($ip) {
            return $this->redirectWithMessage('/admin/firewall', "IP-адрес {$ip} успешно разблокирован.", 'success');
        }

        return $this->redirectBack('/admin/firewall');
    }

    // =========================================================================
    // ИНСТРУМЕНТЫ
    // =========================================================================

    public function tools(): ViewResponse
    {
        return $this->render('tools', [
            'title' => 'Инструменты разработчика фреймворка'
        ]);
    }

    public function compileAssets(): RedirectResponse
    {
        $this->service(AdminToolsService::class)->compileAssets();
        return $this->redirectWithMessage('/admin/tools', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!', 'success');
    }

    public function clearFileLogs(): RedirectResponse
    {
        $count = $this->service(AdminToolsService::class)->clearFileLogs();
        return $this->redirectWithMessage('/admin/tools', "Текстовые логи успешно очищены (обнулено файлов: {$count}).", 'success');
    }

    public function clearDbAudit(): RedirectResponse
    {
        if ($this->service(AdminAuditService::class)->clearAuditLogs()) {
            $this->audit()->log('admin.tools_clear_db', 'Администратор выполнил полную очистку (TRUNCATE) таблицы аудита в базе данных', 'admin');
            return $this->redirectWithMessage('/admin/tools', 'Таблица логов аудита в базе данных успешно и полностью очищена.', 'success');
        }

        MessageBag::flashMessage('error', 'Не удалось очистить таблицу в БД.');
        return $this->redirectWithMessage('/admin/tools', 'Не удалось очистить таблицу в БД.', 'error');
    }

    public function cacheRoutes(): RedirectResponse
    {
        $router = $this->router();
        $this->service(AdminToolsService::class)->cacheRoutes($router);
        return $this->redirectWithMessage('/admin/tools', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.', 'success');
    }

    public function clearCacheRoutes(): RedirectResponse
    {
        $router = $this->router();
        $this->service(AdminToolsService::class)->clearCacheRoutes($router);
        return $this->redirectWithMessage('/admin/tools', 'Кэш маршрутов успешно сброшен.', 'success');
    }

    public function sendTestEmail(): RedirectResponse
    {
        $email = $this->request->getParams('email');

        if (!$email) {
            MessageBag::flashMessage('error', 'Не удалось определить email администратора.');
            return $this->redirectWithMessage('/admin/tools', 'Не удалось определить email администратора.', 'error');
        }

        $error = $this->service(AdminToolsService::class)->sendTestEmail($email);

        if ($error === null) {
            return $this->redirectWithMessage('/admin/tools', 'Тестовое письмо отправлено успешно на ' . e($email), 'success');
        }

        MessageBag::flashMessage('error', $error);
        return $this->redirectWithMessage('/admin/tools', $error, 'error');
    }

    public function recalculateConfidenceScore(): JsonResponse
    {
        try {
            if (ob_get_level()) {
                ob_clean();
            }

            $offset = (int)$this->request->getParams('offset', 0);
            $batchSize = 1000;

            $result = $this->service(AdminToolsService::class)->recalculateConfidenceScoreBatch($offset, $batchSize);

            return $this->json([
                'success' => true,
                'processed' => $result['processed'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'nextOffset' => $result['nextOffset'],
            ]);
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.recalculateConfidence');

            return $this->json([
                'success' => false,
                'error' => 'Ошибка сервера: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // ПРИГЛАШЕНИЯ
    // =========================================================================

    public function invitationsIndex(): ViewResponse
    {
        $status = $this->request->query('status', 'pending');

        return $this->render('invitations', [
            'title' => 'Запросы приглашений',
            'requests' => $this->service(AdminInvitationService::class)->getRequests($status),
            'currentStatus' => $status
        ]);
    }

    public function approveInvitation(int $id): RedirectResponse
    {
        if ($this->service(AdminInvitationService::class)->approveRequest($id)) {
            return $this->redirectWithMessage('/admin/invitations?status=pending', 'Запрос одобрен.', 'success');
        }

        MessageBag::flashMessage('error', 'Не удалось одобрить запрос.');
        return $this->redirectWithMessage('/admin/invitations?status=pending', 'Не удалось одобрить запрос.', 'error');
    }

    public function rejectInvitation(int $id): RedirectResponse
    {
        if ($this->service(AdminInvitationService::class)->rejectRequest($id)) {
            return $this->redirectWithMessage('/admin/invitations?status=pending', 'Запрос отклонён.', 'success');
        }

        MessageBag::flashMessage('error', 'Не удалось отклонить запрос.');
        return $this->redirectWithMessage('/admin/invitations?status=pending', 'Не удалось отклонить запрос.', 'error');
    }
}