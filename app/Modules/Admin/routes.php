<?php

/**
 * Маршруты модуля Admin
 * 
 * Все маршруты защищены middleware:
 * - `web`   → CSRF-защита
 * - `admin` → проверка авторизации + роль администратора
 * 
 * @var \W3a\Core\Router $router
 */

use App\Modules\Admin\Controllers\AdminController;

// =========================================================================
// API-МАРШРУТЫ (без префикса /admin, отдельная группа)
// =========================================================================

$router->group(['middleware' => ['web', 'admin']], function ($router) {
    $router->add(
        'GET',
        '/api/admin/security-alerts',
        AdminController::class . '@getSecurityAlertsApi',
        'api.admin.security_alerts'
    );
});

// =========================================================================
// ОСНОВНЫЕ МАРШРУТЫ АДМИНКИ (префикс /admin)
// =========================================================================

$router->group(['middleware' => ['web', 'admin'], 'prefix' => '/admin'], function ($router) {

    // -------------------------------------------------------------------------
    // Dashboard и статистика
    // -------------------------------------------------------------------------
    $router->add('GET', '', AdminController::class . '@index', 'admin.dashboard');
    $router->add('GET', '/audit', AdminController::class . '@auditLogs', 'admin.audit');

    // -------------------------------------------------------------------------
    // Управление пользователями
    // -------------------------------------------------------------------------
    $router->add('GET', '/users', AdminController::class . '@users', 'admin.users');
    $router->add('GET', '/users/{id}/edit', AdminController::class . '@editUser', 'admin.users.edit');
    $router->add('POST', '/users/{id}/edit', AdminController::class . '@updateUser', 'admin.users.edit.submit');
    $router->add('POST', '/users/{id}/avatar/delete', AdminController::class . '@deleteUserAvatar', 'admin.users.avatar.delete');
    $router->add('POST', '/users/{id}/toggle-status', AdminController::class . '@toggleUserStatus', 'admin.users.toggle_status');
    $router->add('POST', '/users/{id}/archive', AdminController::class . '@archiveUser', 'admin.users.archive');
    $router->add('POST', '/users/{id}/restore', AdminController::class . '@restoreUser', 'admin.users.restore');

    // -------------------------------------------------------------------------
    // Управление тегами
    // -------------------------------------------------------------------------
    $router->add('GET', '/tags', AdminController::class . '@tagsIndex', 'admin.tags');
    $router->add('GET', '/tags/create', AdminController::class . '@showTagCreateForm', 'admin.tags.create');
    $router->add('POST', '/tags/create', AdminController::class . '@createTag', 'admin.tags.create.submit');
    $router->add('GET', '/tags/{id}/edit', AdminController::class . '@showTagEditForm', 'admin.tags.edit');
    $router->add('POST', '/tags/{id}/edit', AdminController::class . '@updateTag', 'admin.tags.edit.submit');
    $router->add('POST', '/tags/{id}/delete', AdminController::class . '@deleteTag', 'admin.tags.delete');
    $router->add('POST', '/tags/{id}/restore', AdminController::class . '@restoreTag', 'admin.tags.restore');

    // -------------------------------------------------------------------------
    // Управление категориями
    // -------------------------------------------------------------------------
    $router->add('GET', '/categories', AdminController::class . '@categoriesIndex', 'admin.categories');
    $router->add('GET', '/categories/create', AdminController::class . '@showCategoryCreateForm', 'admin.categories.create');
    $router->add('POST', '/categories/create', AdminController::class . '@createCategory', 'admin.categories.create.submit');
    $router->add('GET', '/categories/{id}/edit', AdminController::class . '@showCategoryEditForm', 'admin.categories.edit');
    $router->add('POST', '/categories/{id}/edit', AdminController::class . '@updateCategory', 'admin.categories.edit.submit');
    $router->add('POST', '/categories/{id}/delete', AdminController::class . '@deleteCategory', 'admin.categories.delete');

    // -------------------------------------------------------------------------
    // Управление приглашениями
    // -------------------------------------------------------------------------
    $router->add('GET', '/invitations', AdminController::class . '@invitationsIndex', 'admin.invitations');
    $router->add('POST', '/invitations/{id}/approve', AdminController::class . '@approveInvitation', 'admin.invitations.approve');
    $router->add('POST', '/invitations/{id}/reject', AdminController::class . '@rejectInvitation', 'admin.invitations.reject');


    // -------------------------------------------------------------------------
    // Пересчет confidence_score по формуле Вильсона
    // -------------------------------------------------------------------------
    $router->add('POST', '/tools/recalculate-confidence-score', AdminController::class . '@recalculateConfidenceScore', 'admin.tools.recalculate_confidence_score');

    // -------------------------------------------------------------------------
    // Firewall (IP-блокировки)
    // -------------------------------------------------------------------------
    $router->add('GET', '/firewall', AdminController::class . '@firewallIndex', 'admin.firewall');
    $router->add('POST', '/firewall/ban', AdminController::class . '@banIp', 'admin.firewall.ban');
    $router->add('POST', '/firewall/{id}/unban', AdminController::class . '@unbanIp', 'admin.firewall.unban');

    // -------------------------------------------------------------------------
    // Инструменты администратора
    // -------------------------------------------------------------------------
    $router->add('GET', '/tools', AdminController::class . '@tools', 'admin.tools');

    // Компиляция ресурсов
    $router->add('POST', '/tools/compile-assets', AdminController::class . '@compileAssets', 'admin.tools.compile_assets');

    // Очистка логов и данных
    $router->add('POST', '/tools/clear-file-logs', AdminController::class . '@clearFileLogs', 'admin.tools.clear_file_logs');
    $router->add('POST', '/tools/clear-db-audit', AdminController::class . '@clearDbAudit', 'admin.tools.clear_db_audit');

    // Управление кэшем маршрутов
    $router->add('POST', '/tools/cache-routes', AdminController::class . '@cacheRoutes', 'admin.tools.cache_routes');
    $router->add('POST', '/tools/clear-cache-routes', AdminController::class . '@clearCacheRoutes', 'admin.tools.clear_cache_routes');

    // Тестирование почты
    $router->add('POST', '/tools/send-test-email', AdminController::class . '@sendTestEmail', 'admin.tools.send_test_email');
	
	
	// -------------------------------------------------------------------------
	// Управление Wiki страницами
	// -------------------------------------------------------------------------
	$router->add('GET', '/wiki', AdminController::class . '@wikiIndex', 'admin.wiki');
	$router->add('POST', '/wiki/{id}/delete', AdminController::class . '@deleteWikiPage', 'admin.wiki.delete');
	$router->add('POST', '/wiki/{id}/restore', AdminController::class . '@restoreWikiPage', 'admin.wiki.restore');
});
