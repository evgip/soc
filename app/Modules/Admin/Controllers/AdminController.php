<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use W3a\Core\Support\Audit;
use W3a\Core\Http\Router;
use W3a\Core\Exceptions\JsonResponseException;

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
 * 
 * Обрабатывает все функции админ-панели:
 * - Управление пользователями (редактирование, бан, архив)
 * - Управление тегами и категориями
 * - Управление wiki страницами
 * - Журнал аудита и security alerts
 * - Firewall (бан IP)
 * - Инструменты разработчика (кэш, логи, почта)
 * - Запросы приглашений
 * 
 * Все действия логируются через Audit сервис.
 * Все маршруты защищены middleware ['web', 'auth', 'admin'].
 */
class AdminController extends BaseController
{
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить Audit из контейнера
     */
    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

    /**
     * Получить WikiPage из контейнера
     */
    private function wikiPage(): WikiPage
    {
        return $this->container->get(WikiPage::class);
    }

    /**
     * Получить Router из контейнера
     */
    private function router(): Router
    {
        return $this->container->get(Router::class);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Главная страница админ-панели (GET /admin).
     * 
     * Показывает общую статистику: количество пользователей и администраторов.
     */
    public function index(): void
    {
        $users = $this->service(AdminUserService::class)->getAllUsers();

        $this->render('dashboard', [
            'title' => 'Панель управления',
            'totalUsers' => count($users),
            'totalAdmins' => count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'))
        ]);
    }

    // =========================================================================
    // ПОЛЬЗОВАТЕЛИ
    // =========================================================================

    /**
     * Список всех пользователей (GET /admin/users).
     */
    public function users(): void
    {
        $user = $this->service(AdminUserService::class)->getAllUsers();

        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $user
        ]);
    }

    /**
     * Список пользователей с админскими правами (GET /admin/users/index).
     * 
     * Ограничивает выборку до 100 записей для производительности.
     */
    public function usersIndex(): void
    {
        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $this->service(AdminUserService::class)->getAdminUsersList(100),
            'request' => $this->request
        ]);
    }

    /**
     * Форма редактирования профиля пользователя (GET /admin/users/{id}/edit).
     * 
     * Если пользователь не найден — редирект на список.
     */
    public function editUser(string $id): void
    {
        $user = $this->service(AdminUserService::class)->findUser((int)$id);

        if (!$user) {
            $this->redirectBack('/admin/users');
            return;
        }

        $this->render('user_edit_panel', [
            'title' => 'Модерация профиля: ' . e($user['username']),
            'userItem' => $user,
            'request' => $this->request
        ]);
    }

    /**
     * Обновление данных профиля пользователя (POST /admin/users/{id}).
     * 
     * Обновляет email, роль и биографию. После успешного обновления
     * возвращает администратора на список пользователей с flash-сообщением.
     */
    public function updateUser(string $id): void
    {
        $this->service(AdminUserService::class)->updateUserProfile((int)$id, [
            'email' => $this->request->getParams('email'),
            'role' => $this->request->getParams('role'),
            'bio' => $this->request->getParams('bio'),
        ]);

        $this->redirectWithMessage('/admin/users', 'Данные профиля пользователя успешно изменены администратором.', 'success');
    }

    /**
     * Архивация пользователя (POST /admin/users/{id}/archive).
     * 
     * Помечает пользователя как архивного. Действие выполняется от имени
     * текущего администратора и логируется.
     */
    public function archiveUser(string $id): void
    {
        $userContext = $this->getUserContext();
        try {
            $this->service(AdminUserService::class)->archiveUser((int)$id, $userContext['id']);
            $this->redirectWithMessage('/admin/users', 'Пользователь успешно отправлен в архив.', 'success');
        } catch (AdminUserException $e) {
            $this->redirectWithMessage('/admin/users', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.archiveUser');
            $this->redirectWithMessage('/admin/users', 'Произошла ошибка при архивации.', 'error');
        }
    }

    /**
     * Восстановление пользователя из архива (POST /admin/users/{id}/restore).
     */
    public function restoreUser(string $id): void
    {
        $this->service(AdminUserService::class)->restoreUser((int)$id);
        $this->redirectWithMessage('/admin/users', 'Аккаунт пользователя успешно восстановлен из архива.', 'success');
    }

    /**
     * Переключение статуса блокировки пользователя (POST /admin/users/{id}/toggle-status).
     * 
     * Возвращаемые значения сервиса:
     * - -2: ошибка (уже установлена в сервисе)
     * - -1: пользователь не найден
     * -  0: пользователь заблокирован
     * -  1: доступ восстановлен
     */
    public function toggleUserStatus(string $id): void
    {
        $userContext = $this->getUserContext();
        try {
            $result = $this->service(AdminUserService::class)->toggleUserStatus((int)$id, $userContext['id']);
            
            if ($result === 0) {
                $this->redirectWithMessage('/admin/users', 'Пользователь успешно заблокирован.', 'success');
            } else {
                $this->redirectWithMessage('/admin/users', 'Доступ для пользователя успешно восстановлен.', 'success');
            }
        } catch (AdminUserException | AdminValidationException $e) {
            $this->redirectWithMessage('/admin/users', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.toggleUserStatus');
            $this->redirectWithMessage('/admin/users', 'Произошла ошибка.', 'error');
        }
    }

    /**
     * Удаление аватара пользователя (POST /admin/users/{id}/delete-avatar).
     * 
     * После удаления возвращает на страницу редактирования пользователя.
     */
    public function deleteUserAvatar(string $id): void
    {
        $userId = (int)$id;

        if ($this->service(AdminUserService::class)->deleteUserAvatar($userId)) {
            $this->redirectWithMessage("/admin/users/{$userId}/edit", 'Аватар пользователя успешно удален.', 'success');
            return;
        }

        $this->redirect("/admin/users/{$userId}/edit");
    }

    // =========================================================================
    // ТЕГИ
    // =========================================================================

    /**
     * Список всех тегов (GET /admin/tags).
     */
    public function tagsIndex(): void
    {
        $this->render('tags_list', [
            'title' => 'Управление тегами',
            'tags' => $this->service(AdminTagService::class)->getAllTags()
        ]);
    }

    /**
     * Форма создания нового тега (GET /admin/tags/create).
     */
    public function showTagCreateForm(): void
    {
        $this->render('tag_create', [
            'title' => 'Создание нового тега',
            'request' => $this->request
        ]);
    }

    /**
     * Создание нового тега (POST /admin/tags).
     * 
     * При успехе редиректит на список тегов, при ошибке — возвращает на форму.
     */
    public function createTag(): void
    {
        try {
            $this->service(AdminTagService::class)->createTag([
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'is_media' => $this->request->post('is_media') !== null ? 1 : 0,
                'category_id' => $this->request->getParams('category_id'),
            ]);

            $this->redirectWithMessage('/admin/tags', 'Тег успешно добавлен.', 'success');
        } catch (AdminValidationException $e) {
            $this->redirectWithMessage('/admin/tags/create', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.createTag');
            $this->redirectWithMessage('/admin/tags/create', 'Произошла ошибка при создании тега.', 'error');
        }
    }

    /**
     * Форма редактирования тега (GET /admin/tags/{id}/edit).
     * 
     * Если тег не найден — редирект на список.
     */
    public function showTagEditForm(string $id): void
    {
        $tag = $this->service(AdminTagService::class)->getTagById((int)$id);

        if (!$tag) {
            $this->redirectBack('/admin/tags');
            return;
        }

        $this->render('tag_edit', [
            'title' => 'Редактирование тега #' . e($tag['slug']),
            'tagItem' => $tag,
            'request' => $this->request
        ]);
    }

    /**
     * Обновление параметров тега (POST /admin/tags/{id}).
     * 
     * Обновляет название, slug, описание, флаг is_media, категорию
     * и модификатор hotness. При ошибке возвращает на форму редактирования.
     */
    public function updateTag(string $id): void
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

            $this->redirectWithMessage('/admin/tags', 'Параметры тега сохранены.', 'success');
        } catch (AdminValidationException $e) {
            $this->redirectWithMessage("/admin/tags/{$tagId}/edit", $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.updateTag');
            $this->redirectWithMessage("/admin/tags/{$tagId}/edit", 'Произошла ошибка при обновлении.', 'error');
        }
    }

    /**
     * Мягкое удаление тега (POST /admin/tags/{id}/delete).
     * 
     * Тег помечается как удалённый, но остаётся в базе данных.
     * Может быть восстановлен позже.
     */
    public function deleteTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->softDeleteTag($tagId);

        if ($success) {
            $this->redirectWithMessage('/admin/tags', 'Тег успешно удален (перемещен в архив).', 'success');
            return;
        }

        $this->redirectWithMessage('/admin/tags', 'Не удалось удалить тег.', 'error');
    }

    /**
     * Восстановление тега из архива (POST /admin/tags/{id}/restore).
     */
    public function restoreTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->restoreTag($tagId);

        if ($success) {
            $this->redirectWithMessage('/admin/tags', 'Тег успешно восстановлен.', 'success');
            return;
        }

        $this->redirectWithMessage('/admin/tags', 'Не удалось восстановить тег.', 'error');
    }

    // =========================================================================
    // КАТЕГОРИИ
    // =========================================================================

    /**
     * Список всех категорий тегов (GET /admin/categories).
     */
    public function categoriesIndex(): void
    {
        $this->render('categories_list', [
            'title' => 'Управление категориями тегов',
            'categories' => $this->service(AdminCategoryService::class)->getCategoriesList()
        ]);
    }

    /**
     * Форма создания новой категории (GET /admin/categories/create).
     */
    public function showCategoryCreateForm(): void
    {
        $this->render('category_create', [
            'title' => 'Создание новой категории',
            'request' => $this->request
        ]);
    }

    /**
     * Создание новой категории (POST /admin/categories).
     * 
     * При успехе редиректит на список категорий, при ошибке — возвращает на форму.
     */
    public function createCategory(): void
    {
        try {
            $this->service(AdminCategoryService::class)->createCategory([
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'sort_order' => $this->request->getParams('sort_order'),
            ]);

            $this->redirectWithMessage('/admin/categories', 'Категория успешно создана.', 'success');
        } catch (AdminValidationException $e) {
            $this->redirectWithMessage('/admin/categories/create', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.createCategory');
            $this->redirectWithMessage('/admin/categories/create', 'Произошла ошибка.', 'error');
        }
    }

    /**
     * Форма редактирования категории (GET /admin/categories/{id}/edit).
     * 
     * Если категория не найдена — редирект на список с flash-сообщением.
     */
    public function showCategoryEditForm(string $id): void
    {
        $category = $this->service(AdminCategoryService::class)->getCategoryById((int)$id);

        if (!$category) {
            $this->backWithMessage('Категория не найдена.', 'error', '/admin/categories');
            return;
        }

        $this->render('category_edit', [
            'title' => 'Редактирование категории: ' . e($category['name']),
            'categoryItem' => $category,
            'request' => $this->request
        ]);
    }

    /**
     * Обновление параметров категории (POST /admin/categories/{id}).
     * 
     * Обновляет название, slug, описание и порядок сортировки.
     * При ошибке возвращает на форму редактирования.
     */
    public function updateCategory(string $id): void
    {
        $categoryId = (int)$id;
        try {
            $this->service(AdminCategoryService::class)->updateCategory($categoryId, [
                'name' => $this->request->getParams('name'),
                'slug' => $this->request->getParams('slug'),
                'description' => $this->request->getParams('description'),
                'sort_order' => $this->request->getParams('sort_order'),
            ]);

            $this->redirectWithMessage('/admin/categories', 'Категория успешно обновлена.', 'success');
        } catch (AdminValidationException $e) {
            $this->redirectWithMessage("/admin/categories/{$categoryId}/edit", $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.updateCategory');
            $this->redirectWithMessage("/admin/categories/{$categoryId}/edit", 'Произошла ошибка.', 'error');
        }
    }

    /**
     * Удаление категории (POST /admin/categories/{id}/delete).
     */
    public function deleteCategory(string $id): void
    {
        try {
            $this->service(AdminCategoryService::class)->deleteCategory((int)$id);
            $this->redirectWithMessage('/admin/categories', 'Категория успешно удалена.', 'success');
        } catch (AdminValidationException $e) {
            $this->redirectWithMessage('/admin/categories', $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->logError($e, 'Admin.deleteCategory');
            $this->redirectWithMessage('/admin/categories', 'Произошла ошибка при удалении.', 'error');
        }
    }

    // =========================================================================
    // WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Список всех wiki страниц с пагинацией (GET /admin/wiki).
     * 
     * Показывает 50 страниц на странице, включая информацию
     * о количестве удалённых страниц.
     */
    public function wikiIndex(): void
    {
        $wikiPage = $this->wikiPage();

        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $pages = $wikiPage->getAllPagesWithTags($perPage, $offset);
        $totalPages = $wikiPage->getTotalPagesCount();
        $deletedPages = $wikiPage->getDeletedPagesCount();

        $this->render('wiki_list', [
            'title' => 'Управление Wiki страницами',
            'pages' => $pages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'deletedPages' => $deletedPages,
            'totalPagesCount' => ceil($totalPages / $perPage),
        ]);
    }

    /**
     * Мягкое удаление wiki страницы (POST /admin/wiki/{id}/delete).
     * 
     * Страница помечается как удалённая, но остаётся в базе данных.
     * Действие логируется в аудит с указанием ID администратора.
     */
    public function deleteWikiPage(string $id): void
    {
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);

        if (!$page) {
            $this->backWithMessage('Wiki страница не найдена', 'error', '/admin/wiki');
            return;
        }

        if ($wikiPage->softDelete((int)$id)) {
            $userContext = $this->getUserContext();

            $this->audit()->log('admin.wiki.deleted', 'Wiki страница удалена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => $userContext['id'],
            ]);

            $this->redirectWithMessage('/admin/wiki', "Wiki страница «{$page['title']}» удалена", 'success');
            return;
        }

        $this->redirectWithMessage('/admin/wiki', 'Ошибка при удалении wiki страницы', 'error');
    }

    /**
     * Восстановление wiki страницы из архива (POST /admin/wiki/{id}/restore).
     * 
     * Действие логируется в аудит с указанием ID администратора.
     */
    public function restoreWikiPage(string $id): void
    {
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);

        if (!$page) {
            $this->backWithMessage('Wiki страница не найдена', 'error', '/admin/wiki');
            return;
        }

        if ($wikiPage->restore((int)$id)) {
            $userContext = $this->getUserContext();

            $this->audit()->log('admin.wiki.restored', 'Wiki страница восстановлена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => $userContext['id'],
            ]);

            $this->redirectWithMessage('/admin/wiki', "Wiki страница «{$page['title']}» восстановлена", 'success');
            return;
        }

        $this->redirectWithMessage('/admin/wiki', 'Ошибка при восстановлении wiki страницы', 'error');
    }

    // =========================================================================
    // АУДИТ
    // =========================================================================

    /**
     * Журнал аудита системы с фильтрами (GET /admin/audit).
     * 
     * Поддерживает фильтрацию по:
     * - ID пользователя (filter_user_id)
     * - Типу действия (filter_action)
     * - Поисковому запросу (search)
     * - Категории (category)
     * 
     * Пагинация: 25 записей на странице.
     */
    public function auditLogs(): void
    {
        $filterUserIdRaw = $this->request->query('filter_user_id');
        $filterUserId = ($filterUserIdRaw !== null && $filterUserIdRaw !== '')
            ? (int)$filterUserIdRaw
            : null;

        $filterActionRaw = $this->request->query('filter_action');
        $filterAction = ($filterActionRaw !== null && $filterActionRaw !== '')
            ? trim($filterActionRaw)
            : null;

        $filterCategoryRaw = $this->request->query('category');
        $filterCategory = ($filterCategoryRaw !== null && $filterCategoryRaw !== '')
            ? trim($filterCategoryRaw)
            : null;

        $searchQueryRaw = $this->request->query('search');
        $searchQuery = ($searchQueryRaw !== null && $searchQueryRaw !== '')
            ? trim($searchQueryRaw)
            : null;

        $currentPage = max(1, (int)$this->request->query('page', 1));
        $perPage = 25;
        $offset = ($currentPage - 1) * $perPage;

        $auditService = $this->service(AdminAuditService::class);

        $logs = $auditService->getFilteredLogs(
            $perPage,
            $offset,
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );

        $totalLogs = $auditService->getFilteredCount(
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );

        $totalPages = max(1, (int)ceil($totalLogs / $perPage));

        $this->render('audit_list', [
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

    /**
     * API для получения последних security alerts (GET /admin/security-alerts).
     * 
     * Возвращает JSON с недавними событиями безопасности для отображения
     * в админ-панели в реальном времени.
     */
    public function getSecurityAlertsApi(): void
    {
        $this->json([
            'status' => 'success',
            'alerts' => $this->service(AdminAuditService::class)->getRecentSecurityAlerts(),
            'timestamp' => time()
        ]);
    }

    // =========================================================================
    // FIREWALL
    // =========================================================================

    /**
     * Страница управления firewall (GET /admin/firewall).
     * 
     * Показывает список заблокированных IP-адресов.
     */
    public function firewallIndex(): void
    {
        $this->render('firewall', [
            'title' => 'Сетевой экран (Firewall)',
            'bannedIps' => $this->service(AdminFirewallService::class)->getBannedIps(),
            'request' => $this->request
        ]);
    }

    /**
     * Блокировка IP-адреса (POST /admin/firewall/ban).
     * 
     * Валидирует формат IP и проверяет, не заблокирован ли он уже.
     * При ошибке показывает соответствующее сообщение.
     */
    public function banIp(): void
    {
        $ip = trim($this->request->getParams('ip_address'));
        $reason = trim($this->request->getParams('reason')) ?: 'Нарушение правил сообщества';

        if ($this->service(AdminFirewallService::class)->banIp($ip, $reason)) {
            $this->redirectWithMessage('/admin/firewall', "IP-адрес {$ip} успешно внесен в черный список.", 'success');
            return;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->redirectWithMessage('/admin/firewall', 'Указан некорректный IP-адрес.', 'error');
            return;
        }

        $this->redirectWithMessage('/admin/firewall', 'Этот IP-адрес уже заблокирован.', 'error');
    }

    /**
     * Разблокировка IP-адреса (POST /admin/firewall/unban/{id}).
     */
    public function unbanIp(string $id): void
    {
        $ip = $this->service(AdminFirewallService::class)->unbanIp((int)$id);

        if ($ip) {
            $this->redirectWithMessage('/admin/firewall', "IP-адрес {$ip} успешно разблокирован.", 'success');
            return;
        }

        $this->redirectBack('/admin/firewall');
    }

    // =========================================================================
    // ИНСТРУМЕНТЫ
    // =========================================================================

    /**
     * Страница инструментов разработчика (GET /admin/tools).
     */
    public function tools(): void
    {
        $this->render('tools', [
            'title' => 'Инструменты разработчика фреймворка'
        ]);
    }

    /**
     * Компиляция CSS ассетов всех модулей (POST /admin/tools/compile-assets).
     * 
     * Находит все CSS файлы модулей, объединяет и сжимает их.
     * Результат кэшируется для ускорения загрузки страниц.
     */
    public function compileAssets(): void
    {
        $this->service(AdminToolsService::class)->compileAssets();
        $this->redirectWithMessage('/admin/tools', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!', 'success');
    }

    /**
     * Очистка текстовых файлов логов (POST /admin/tools/clear-logs).
     * 
     * Обнуляет файлы app.log и audit.log в storage/logs/.
     */
    public function clearFileLogs(): void
    {
        $count = $this->service(AdminToolsService::class)->clearFileLogs();
        $this->redirectWithMessage('/admin/tools', "Текстовые логи успешно очищены (обнулено файлов: {$count}).", 'success');
    }

    /**
     * Полная очистка таблицы аудита в БД (POST /admin/tools/clear-audit).
     * 
     * Выполняет TRUNCATE таблицы audit_logs. Действие необратимо
     * и логируется перед выполнением.
     */
    public function clearDbAudit(): void
    {
        if ($this->service(AdminAuditService::class)->clearAuditLogs()) {
            $this->audit()->log('admin.tools_clear_db', 'Администратор выполнил полную очистку (TRUNCATE) таблицы аудита в базе данных', 'admin');
            $this->redirectWithMessage('/admin/tools', 'Таблица логов аудита в базе данных успешно и полностью очищена.', 'success');
            return;
        }

        $this->redirectWithMessage('/admin/tools', 'Не удалось очистить таблицу в БД.', 'error');
    }

    /**
     * Кэширование маршрутов всех модулей (POST /admin/tools/cache-routes).
     * 
     * Компилирует маршруты в кэш-файл для ускорения обработки запросов.
     * Router получается из DI-контейнера (без использования global).
     */
    public function cacheRoutes(): void
    {
        $router = $this->router();
        $this->service(AdminToolsService::class)->cacheRoutes($router);
        $this->redirectWithMessage('/admin/tools', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.', 'success');
    }

    /**
     * Сброс кэша маршрутов (POST /admin/tools/clear-cache-routes).
     * 
     * Удаляет кэш-файл маршрутов, заставляя систему пересобрать его
     * при следующем запросе.
     * Router получается из DI-контейнера (без использования global).
     */
    public function clearCacheRoutes(): void
    {
        $router = $this->router();
        $this->service(AdminToolsService::class)->clearCacheRoutes($router);
        $this->redirectWithMessage('/admin/tools', 'Кэш маршрутов успешно сброшен.', 'success');
    }

    /**
     * Отправка тестового письма (POST /admin/tools/send-test-email).
     * 
     * Проверяет работоспособность настроек почты (PHP mail() или SMTP).
     */
    public function sendTestEmail(): void
    {
        $email = $this->request->getParams('email');

        if (!$email) {
            $this->redirectWithMessage('/admin/tools', 'Не удалось определить email администратора.', 'error');
            return;
        }

        $error = $this->service(AdminToolsService::class)->sendTestEmail($email);

        if ($error === null) {
            $this->redirectWithMessage('/admin/tools', 'Тестовое письмо отправлено успешно на ' . e($email), 'success');
            return;
        }

        $this->redirectWithMessage('/admin/tools', $error, 'error');
    }

    /**
     * Пересчёт confidence_score для всех комментариев (AJAX POST /admin/tools/recalculate-confidence).
     * 
     * Выполняется батчами по 1000 записей для избежания таймаутов.
     * Возвращает JSON с информацией о прогрессе:
     * - processed: обработано записей
     * - total: всего записей
     * - hasMore: остались ли ещё записи
     * - nextOffset: смещение для следующего батча
     * 
     * ВАЖНО: JsonResponseException пробрасывается дальше,
     * чтобы Application корректно обработал ответ.
     */
    public function recalculateConfidenceScore(): void
    {
        try {
            if (ob_get_level()) {
                ob_clean();
            }

            $offset = (int)$this->request->getParams('offset', 0);
            $batchSize = 1000;

            $result = $this->service(AdminToolsService::class)->recalculateConfidenceScoreBatch($offset, $batchSize);

            $this->json([
                'success' => true,
                'processed' => $result['processed'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'nextOffset' => $result['nextOffset'],
            ]);
        } catch (JsonResponseException $e) {
            // ✅ НЕ перехватываем JsonResponseException — Application обработает
            throw $e;
        } catch (\Throwable $e) {
            // ✅ Логируем реальную ошибку через единый метод из базового Controller
            $this->logError($e, 'Admin.recalculateConfidence');

            $this->json([
                'success' => false,
                'error' => 'Ошибка сервера: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // ПРИГЛАШЕНИЯ
    // =========================================================================

    /**
     * Список запросов приглашений (GET /admin/invitations).
     * 
     * Фильтруется по статусу: pending (ожидающие), approved (одобренные), rejected (отклонённые).
     */
    public function invitationsIndex(): void
    {
        $status = $this->request->query('status', 'pending');

        $this->render('invitations', [
            'title' => 'Запросы приглашений',
            'requests' => $this->service(AdminInvitationService::class)->getRequests($status),
            'currentStatus' => $status
        ], 'Invitations');
    }

    /**
     * Одобрение запроса приглашения (POST /admin/invitations/{id}/approve).
     * 
     * После одобрения пользователь получает приглашение на регистрацию.
     */
    public function approveInvitation(int $id): void
    {
        if ($this->service(AdminInvitationService::class)->approveRequest($id)) {
            $this->redirectWithMessage('/admin/invitations?status=pending', 'Запрос одобрен.', 'success');
            return;
        }

        $this->redirectWithMessage('/admin/invitations?status=pending', 'Не удалось одобрить запрос.', 'error');
    }

    /**
     * Отклонение запроса приглашения (POST /admin/invitations/{id}/reject).
     */
    public function rejectInvitation(int $id): void
    {
        if ($this->service(AdminInvitationService::class)->rejectRequest($id)) {
            $this->redirectWithMessage('/admin/invitations?status=pending', 'Запрос отклонён.', 'success');
            return;
        }

        $this->redirectWithMessage('/admin/invitations?status=pending', 'Не удалось отклонить запрос.', 'error');
    }
}