<?php

declare(strict_types=1);

namespace App\Modules\Messages\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Http\Response;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\ViewResponse;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;

/**
 * Контроллер личных сообщений.
 */
class MessagesController extends BaseController
{
    /**
     * Получить экземпляр Session из DI-контейнера.
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    // =========================================================================
    // СПИСОК ДИАЛОГОВ
    // =========================================================================

    /**
     * Список всех диалогов текущего пользователя
     */
    public function index(): ViewResponse
    {
        $userContext = $this->getUserContext();
        $chats = $this->service(ConversationService::class)->getUserConversations($userContext['id']);

        return $this->render('index', [
            'title' => 'Мои диалоги',
            'chats' => $chats
        ]);
    }

    // =========================================================================
    // ПРОСМОТР ДИАЛОГА
    // =========================================================================

    /**
     * Просмотр диалога с пагинацией сообщений
     */
    public function showDialog(string $id): Response
    {
        $conversationId = (int)$id;
        $userContext = $this->getUserContext();

        $chatRoom = $this->service(ConversationService::class)->getConversationWithAccessCheck($conversationId, $userContext['id']);
        if (!$chatRoom) {
            return $this->redirectBack('/messages');
        }

        $this->service(MessageService::class)->markAsRead($conversationId, $userContext['id']);

        $currentPage = max(1, (int)$this->request->getParams('chat_page', 1));
        $perPage = config('pagination.messages_per_page', 15, 'int');

        $messagesData = $this->service(MessageService::class)->getPaginatedMessages($conversationId, $currentPage, $perPage);
        $recipient = $this->service(ConversationService::class)->getConversationPartner($conversationId, $userContext['id']);

        return $this->render('dialog', [
            'title' => 'Чат с ' . e($recipient['username']),
            'messages' => $messagesData['messages'],
            'recipient' => $recipient,
            'conversationId' => $conversationId,
            'currentPage' => $messagesData['currentPage'],
            'totalPages' => $messagesData['totalPages'],
            'request' => $this->request
        ]);
    }

    // =========================================================================
    // ОТПРАВКА СООБЩЕНИЯ
    // =========================================================================

    /**
     * Отправка сообщения в диалог
     */
    public function sendMessage(): RedirectResponse
    {
        $conversationId = (int)$this->request->getParams('conversation_id');
        $messageText = $this->request->getParams('message_text');
        $userContext = $this->getUserContext();

        $this->service(MessageService::class)->sendMessage($conversationId, $userContext['id'], $messageText);

        return $this->redirect('/messages/chat/' . $conversationId);
    }

    // =========================================================================
    // СОЗДАНИЕ НОВОГО ДИАЛОГА
    // =========================================================================

    /**
     * Создание нового диалога с пользователем
     */
    public function startConversation(string $userId): RedirectResponse
    {
        $userContext = $this->getUserContext();
        $targetUid = (int)$userId;

        try {
            // Пытаемся создать или получить диалог
            $roomId = $this->service(ConversationService::class)->getOrCreateConversation($userContext['id'], $targetUid);
        } catch (\App\Modules\Messages\Exceptions\ConversationException $e) {
            // Ловим бизнес-ошибки (например, "Нельзя создать диалог с самим собой")
            $this->session()->flash('error', $e->getMessage());
            return $this->redirect('/messages');
        } catch (\Throwable $e) {
            // Ловим реальные непредвиденные ошибки и логируем их
            $this->logError($e, 'Messages.startConversation');
            $this->session()->flash('error', 'Произошла непредвиденная ошибка при создании диалога.');
            return $this->redirect('/messages');
        }

        return $this->redirect('/messages/chat/' . $roomId);
    }
}
