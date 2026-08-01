<?php

declare(strict_types=1);

namespace App\Modules\Flags\Controllers;

use App\BaseController;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\JsonResponse;
use W3a\Core\Support\Audit;
use W3a\Core\Support\MessageBag;
use W3a\Core\Exceptions\BadRequestException;
use W3a\Core\Exceptions\NotFoundException;

use App\Modules\Flags\Models\Flag;
use App\Modules\Comments\Models\Comment;

/**
 * Контроллер жалоб (flags) на контент.
 * 
 * Обрабатывает:
 * - Форму подачи жалобы на историю/комментарий
 * - Отправку жалоб с автоматическим скрытием по порогу
 * - Админ-панель для модерации жалоб
 * - AJAX подсчёт количества ожидающих жалоб
 */
class FlagsController extends BaseController
{
    /**
     * Получить Audit из контейнера
     */
    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

    /**
     * Получить модель Flag
     */
    private function flagModel(): Flag
    {
        return $this->service(Flag::class);
    }

    /**
     * GET /flags/report?type=story&id=123
     */
    public function reportForm(string $type, string $id): Response
    {
        $targetId = (int) $id;

        if (!in_array($type, ['story', 'comment'], true) || $targetId <= 0) {
            throw new BadRequestException('Некорректные параметры жалобы');
        }

        $flagModel = $this->flagModel();
        $userContext = $this->getUserContext();

        if ($flagModel->hasUserFlagged($userContext['id'], $type, $targetId)) {
            MessageBag::flashMessage('error', 'Вы уже подавали жалобу на этот контент.');
            return $this->redirect($this->buildTargetUrl($type, $targetId));
        }

        return $this->render('report_form', [
            'title'    => 'Пожаловаться на контент',
            'type'     => $type,
            'targetId' => $targetId,
            'reasons'  => $flagModel->getReasons(),
        ]);
    }

    /**
     * POST /flags/report
     */
    public function submit(): RedirectResponse
    {
        $this->request->validateCsrf();

        $type     = $this->request->getParams('flaggable_type');
        $targetId = (int) $this->request->getParams('flaggable_id');
        $reason   = $this->request->getParams('reason');
        $comment  = $this->request->getParams('comment');

        $userContext = $this->getUserContext();
        $flagModel = $this->flagModel();
        
        $result = $flagModel->submit($userContext['id'], $type, $targetId, $reason, $comment);

        if (!$result['ok']) {
            MessageBag::flashMessage('error', $result['error']);
            return $this->redirect($this->buildTargetUrl($type, $targetId));
        }

        // Логируем успешную жалобу
        $this->audit()->log('flag.submitted', 'Пользователь подал жалобу', 'flags', [
            'type'   => $type,
            'id'     => $targetId,
            'reason' => $reason,
        ]);

        // Логируем автоматическое скрытие, если сработал порог
        if (!empty($result['hidden'])) {
            $this->audit()->log('flag.auto_hidden', 'Контент автоматически скрыт по порогу флагов', 'flags', [
                'type'      => $type,
                'id'        => $targetId,
                'threshold' => $flagModel->getHideThreshold(),
            ]);
        }

        MessageBag::flashMessage('success', 'Спасибо! Ваша жалоба принята. Модераторы рассмотрят её в ближайшее время.');
        return $this->redirect($this->buildTargetUrl($type, $targetId));
    }

    /**
     * GET /admin/flags
     */
    public function adminIndex(): ViewResponse
    {
        $flagModel = $this->flagModel();
        $pending = $flagModel->getPendingFlags();
        $recent  = $flagModel->getAllFlags(50);

        return $this->render('admin_index', [
            'title'        => 'Жалобы пользователей',
            'pendingFlags' => $pending,
            'recentFlags'  => $recent,
            'reasons'      => $flagModel->getReasons(),
            'pendingCount' => count($pending),
            'hideThreshold' => $flagModel->getHideThreshold(),
        ]);
    }

    /**
     * POST /admin/flags/{id}/resolve
     */
    public function resolve(string $id): RedirectResponse
    {
        $this->request->validateCsrf();

        $action = $this->request->getParams('action') ?: 'hide';
        $userContext = $this->getUserContext();

        $flagModel = $this->flagModel();
        $flag = $flagModel->find((int) $id);

        if (!$flag) {
            throw new NotFoundException('Жалоба не найдена');
        }

        if ($action === 'dismiss') {
            $flagModel->dismiss((int) $id, $userContext['id']);
            $this->audit()->log('flag.dismissed', 'Модератор отклонил жалобу', 'flags', ['flag_id' => (int) $id]);

            MessageBag::flashMessage('success', 'Жалоба отклонена. Контент восстановлен.');
            return $this->redirect('/admin/flags');
        }

        $flagModel->resolve((int) $id, $userContext['id']);
        $this->audit()->log('flag.resolved', 'Модератор подтвердил жалобу', 'flags', ['flag_id' => (int) $id]);

        MessageBag::flashMessage('success', 'Жалоба подтверждена. Контент скрыт.');
        return $this->redirect('/admin/flags');
    }

    /**
     * GET /admin/flags/count (AJAX)
     */
    public function pendingCount(): JsonResponse
    {
        $count = $this->flagModel()->getPendingCount();
        
        // 🔥 ИСПРАВЛЕНО: Просто возвращаем JSON, никаких исключений для нормального потока
        return $this->json(['count' => $count]);
    }

    /**
     * Построить URL к целевому контенту (история или комментарий)
     */
    private function buildTargetUrl(string $type, int $targetId): string
    {
        if ($type === 'story') {
            return "/story/{$targetId}";
        }

        $commentModel = $this->service(Comment::class);
        $comment = $commentModel->find($targetId);

        if ($comment && !empty($comment['story_id'])) {
            return "/story/{$comment['story_id']}#comment-block-{$targetId}";
        }

        return '/';
    }
}