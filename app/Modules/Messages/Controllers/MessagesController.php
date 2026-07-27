<?php

declare(strict_types=1);

namespace App\Modules\Messages\Controllers;

use App\BaseController;
use W3a\Core\Session;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;
use App\Modules\Messages\Exceptions\ConversationException;

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
    public function index(): void
    {
        $userContext = $this->getUserContext();
        $chats = $this->service(ConversationService::class)->getUserConversations($userContext['id']);

        $this->render('index', [
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
    public function showDialog(string $id): void
    {
        $conversationId = (int)$id;
        $userContext = $this->getUserContext();

        $chatRoom = $this->service(ConversationService::class)->getConversationWithAccessCheck($conversationId, $userContext['id']);
        if (!$chatRoom) {
            $this->redirectBack('/messages');
            return;
        }

        $this->service(MessageService::class)->markAsRead($conversationId, $userContext['id']);

        $currentPage = max(1, (int)$this->request->getParams('chat_page', 1));
        $perPage = config('pagination.messages_per_page', 15, 'int');

        $messagesData = $this->service(MessageService::class)->getPaginatedMessages($conversationId, $currentPage, $perPage);
        $recipient = $this->service(ConversationService::class)->getConversationPartner($conversationId, $userContext['id']);

        $this->render('dialog', [
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
    public function sendMessage(): void
    {
        $conversationId = (int)$this->request->getParams('conversation_id');
        $messageText = $this->request->getParams('message_text');
        $userContext = $this->getUserContext();

        $this->service(MessageService::class)->sendMessage($conversationId, $userContext['id'], $messageText);

        $this->redirect('/messages/chat/' . $conversationId);
    }

    // =========================================================================
    // СОЗДАНИЕ НОВОГО ДИАЛОГА
    // =========================================================================

    /**
     * Создание нового диалога с пользователем
     */
    public function startConversation(string $userId): void
    {
        $userContext = $this->getUserContext();
        $targetUid = (int)$userId;

        try {
            // Пытаемся создать или получить диалог
            $roomId = $this->service(ConversationService::class)->getOrCreateConversation($userContext['id'], $targetUid);
            
        } catch (\App\Modules\Messages\Exceptions\ConversationException $e) {
            // Ловим бизнес-ошибки (например, "Нельзя создать диалог с самим собой")
            $this->session()->flash('error', $e->getMessage());
            $this->redirect('/messages');
            return; // Обязательно прерываем выполнение
            
        } catch (\Throwable $e) {
            // Ловим реальные непредвиденные ошибки и логируем их
            $this->logError($e, 'Messages.startConversation');
            $this->session()->flash('error', 'Произошла непредвиденная ошибка при создании диалога.');
            $this->redirect('/messages');
            return; // Обязательно прерываем выполнение
        }

        $this->redirect('/messages/chat/' . $roomId);
    }
}