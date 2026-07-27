<?php

declare(strict_types=1);

namespace App\Modules\Flags\Controllers;

use App\BaseController;
use W3a\Core\Session;
use W3a\Core\Audit;
use App\Modules\Flags\Models\Flag;
use App\Modules\Comments\Models\Comment;
use W3a\Core\Exceptions\BadRequestException;
use W3a\Core\Exceptions\NotFoundException;
use W3a\Core\Exceptions\JsonResponseException;

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
     * Получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

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
    public function reportForm(string $type, string $id): void
    {
        $targetId = (int) $id;

        if (!in_array($type, ['story', 'comment'], true) || $targetId <= 0) {
            throw new BadRequestException('Некорректные параметры жалобы');
        }

        $flagModel = $this->flagModel();

        $userContext = $this->getUserContext();

        if ($flagModel->hasUserFlagged($userContext['id'], $type, $targetId)) {

            $this->redirectWithMessage(
                $this->buildTargetUrl($type, $targetId),
                'Вы уже подавали жалобу на этот контент.',
                'error'
            );
            return;
        }

        $this->render('report_form', [
            'title'    => 'Пожаловаться на контент',
            'type'     => $type,
            'targetId' => $targetId,
            'reasons'  => $flagModel->getReasons(),
        ]);
    }

    /**
     * POST /flags/report
     */
    public function submit(): void
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

            $this->redirectWithMessage(
                $this->buildTargetUrl($type, $targetId),
                $result['error'],
                'error'
            );
            return;
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

        $this->redirectWithMessage(
            $this->buildTargetUrl($type, $targetId),
            'Спасибо! Ваша жалоба принята. Модераторы рассмотрят её в ближайшее время.',
            'success'
        );
    }

    /**
     * GET /admin/flags
     */
    public function adminIndex(): void
    {
        $flagModel = $this->flagModel();
        $pending = $flagModel->getPendingFlags();
        $recent  = $flagModel->getAllFlags(50);

        $this->render('admin_index', [
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
    public function resolve(string $id): void
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

            $this->redirectWithMessage('/admin/flags', 'Жалоба отклонена. Контент восстановлен.', 'success');
            return;
        }

        $flagModel->resolve((int) $id, $userContext['id']);
        $this->audit()->log('flag.resolved', 'Модератор подтвердил жалобу', 'flags', ['flag_id' => (int) $id]);

        $this->redirectWithMessage('/admin/flags', 'Жалоба подтверждена. Контент скрыт.', 'success');
    }

    /**
     * GET /admin/flags/count (AJAX)
     */
    public function pendingCount(): void
    {
        $count = $this->flagModel()->getPendingCount();
        throw new JsonResponseException(['count' => $count]);
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
