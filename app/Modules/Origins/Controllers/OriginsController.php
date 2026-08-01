<?php

declare(strict_types=1);

namespace App\Modules\Origins\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Support\Audit;
use W3a\Core\Support\MessageBag; // 🔥 Добавили использование MessageBag

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
    public function index(): ViewResponse
    {
        $domainModel = $this->service(Domain::class);
        $bannedDomains = $domainModel->getBannedDomains();

        return $this->render('index', [
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
    public function adminIndex(): ViewResponse
    {
        $domainModel = $this->service(Domain::class);
        $allDomains = $domainModel->getAllDomains();

        return $this->render('admin_index', [
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
    public function showBanForm(): ViewResponse
    {
        return $this->render('ban_form', [
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
    public function ban(): RedirectResponse
    {
        $this->request->validateCsrf();

        $domain = strtolower(trim($this->request->getParams('domain')));
        $reason = trim($this->request->getParams('ban_reason')) ?: 'Нарушение правил сообщества';

        // Валидация формата домена
        if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain)) {
            MessageBag::flashMessage('error', 'Указан некорректный домен. Пример: example.com');
            return $this->redirect('/admin/domains/create');
        }

        $domainModel = $this->service(Domain::class);
        $userContext = $this->getUserContext();

        if ($domainModel->ban($domain, $reason, $userContext['id'])) {
            $this->audit()->log('admin.domain_banned', "Модератор заблокировал домен: {$domain}", 'admin', [
                'domain' => $domain,
                'reason' => $reason,
            ]);

            MessageBag::flashMessage('success', "Домен «{$domain}» успешно заблокирован.");
            return $this->redirect('/admin/domains');
        }

        MessageBag::flashMessage('error', "Домен «{$domain}» уже заблокирован.");
        return $this->redirect('/admin/domains');
    }

    /**
     * Разблокировка домена (POST /admin/domains/{id}/unban).
     * 
     * Снимает блокировку с домена. Если домен не найден —
     * редирект на список с flash-сообщением.
     * 
     * Действие логируется в аудит с указанием ID домена.
     */
    public function unban(string $id): RedirectResponse
    {
        $this->request->validateCsrf();

        $domainModel = $this->service(Domain::class);
        $domain = $domainModel->find((int) $id);

        if (!$domain) {
            MessageBag::flashMessage('error', 'Домен не найден.');
            return $this->redirect('/admin/domains');
        }

        $domainModel->unban($domain['domain']);

        $this->audit()->log('admin.domain_unbanned', "Модератор разблокировал домен: {$domain['domain']}", 'admin', [
            'domain_id' => (int) $id,
        ]);

        MessageBag::flashMessage('success', "Домен «{$domain['domain']}» успешно разблокирован.");
        return $this->redirect('/admin/domains');
    }
}
