<?php

declare(strict_types=1);

namespace App\Modules\Origins\Controllers;

use App\BaseController;
use W3a\Core\Audit;
use App\Modules\Origins\Models\Domain;

/**
 * Контроллер управления доменами (Origins).
 * 
 * Обрабатывает:
 * - Список заблокированных доменов (публичный)
 * - Админ-панель управления всеми доменами
 * - Блокировку/разблокировку доменов с валидацией
 * 
 * Все действия логируются через Audit сервис.
 * Маршруты админ-панели защищены middleware ['web', 'auth', 'admin'].
 */
class OriginsController extends BaseController
{
    /**
     * Получить Audit из контейнера
     */
    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

    // =========================================================================
    // ПУБЛИЧНЫЙ СПИСОК ЗАБЛОКИРОВАННЫХ ДОМЕНОВ
    // =========================================================================

    /**
     * Список заблокированных доменов (GET /domains).
     * 
     * Показывает публичный список доменов, заблокированных модераторами.
     * Доступен всем пользователям для прозрачности модерации.
     */
    public function index(): void
    {
        $domainModel = $this->service(Domain::class);
        $bannedDomains = $domainModel->getBannedDomains();

        $this->render('index', [
            'title'         => 'Заблокированные домены',
            'bannedDomains' => $bannedDomains,
            'totalBanned'   => count($bannedDomains),
        ]);
    }

    // =========================================================================
    // АДМИН-ПАНЕЛЬ ДОМЕНОВ
    // =========================================================================

    /**
     * Админ-панель управления всеми доменами (GET /admin/domains).
     * 
     * Показывает полный список доменов в системе с информацией
     * о количестве заблокированных.
     */
    public function adminIndex(): void
    {
        $domainModel = $this->service(Domain::class);
        $allDomains = $domainModel->getAllDomains();

        $this->render('admin_index', [
            'title'       => 'Управление доменами',
            'allDomains'  => $allDomains,
            'totalBanned' => $domainModel->getBannedCount(),
        ]);
    }

    // =========================================================================
    // БЛОКИРОВКА ДОМЕНА
    // =========================================================================

    /**
     * Форма блокировки домена (GET /admin/domains/create).
     */
    public function showBanForm(): void
    {
        $this->render('ban_form', [
            'title'   => 'Заблокировать домен',
            'request' => $this->request,
        ]);
    }

    /**
     * Блокировка домена (POST /admin/domains/ban).
     * 
     * Валидирует формат домена по регулярному выражению,
     * проверяет уникальность и блокирует домен с указанием причины.
     * 
     * Действие логируется в аудит с указанием домена и причины.
     */
    public function ban(): void
    {
        $this->request->validateCsrf();

        $domain = strtolower(trim($this->request->getParams('domain')));
        $reason = trim($this->request->getParams('ban_reason')) ?: 'Нарушение правил сообщества';

        // Валидация формата домена
        if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain)) {
            $this->backWithMessage('Указан некорректный домен. Пример: example.com', 'error', '/admin/domains/create');
            return;
        }

        $domainModel = $this->service(Domain::class);
        $userContext = $this->getUserContext();

        if ($domainModel->ban($domain, $reason, $userContext['id'])) {
            $this->audit()->log('admin.domain_banned', "Модератор заблокировал домен: {$domain}", 'admin', [
                'domain' => $domain,
                'reason' => $reason,
            ]);

            $this->redirectWithMessage('/admin/domains', "Домен «{$domain}» успешно заблокирован.", 'success');
            return;
        }

        $this->redirectWithMessage('/admin/domains', "Домен «{$domain}» уже заблокирован.", 'error');
    }

    /**
     * Разблокировка домена (POST /admin/domains/{id}/unban).
     * 
     * Снимает блокировку с домена. Если домен не найден —
     * редирект на список с flash-сообщением.
     * 
     * Действие логируется в аудит с указанием ID домена.
     */
    public function unban(string $id): void
    {
        $this->request->validateCsrf();

        $domainModel = $this->service(Domain::class);
        $domain = $domainModel->find((int) $id);

        if (!$domain) {
            $this->backWithMessage('Домен не найден.', 'error', '/admin/domains');
            return;
        }

        $domainModel->unban($domain['domain']);

        $this->audit()->log('admin.domain_unbanned', "Модератор разблокировал домен: {$domain['domain']}", 'admin', [
            'domain_id' => (int) $id,
        ]);

        $this->redirectWithMessage('/admin/domains', "Домен «{$domain['domain']}» успешно разблокирован.", 'success');
    }
}
