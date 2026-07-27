<?php

namespace App\Modules\Messages\Services;

use W3a\Core\Session;
use App\Modules\Messages\Models\Message;
use App\Modules\Messages\Models\Conversation;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Сервис для работы с сообщениями в диалогах.
 * Инкапсулирует бизнес-логику: отправка сообщений, пагинация,
 * отметка прочитанного, отправка уведомлений.
 */
class MessageService
{
    private Message $messageModel;
    private Conversation $conversationModel;
    private ?NotificationService $notificationService;

	public function __construct(
		?Message $messageModel = null,
		?Conversation $conversationModel = null,
		?NotificationService $notificationService = null
	) {
		$this->messageModel = $messageModel ?? new Message();
		$this->conversationModel = $conversationModel ?? new Conversation();
		$this->notificationService = $notificationService;
	}

    /**
     * Отправить сообщение в диалог.
     * @return int ID созданного сообщения
     */
	public function sendMessage(int $conversationId, int $senderId, string $messageText): ?int
	{
		$messageText = trim($messageText);
		
		// Валидация в сервисе
		if (empty($messageText)) {
			Session::setFlash('error', 'Текст сообщения не может быть пустым');
			return null;
		}

		try {
			// Создаём сообщение
			$messageId = $this->messageModel->create([
				'conversation_id' => $conversationId,
				'sender_id' => $senderId,
				'message' => $messageText
			]);

			// Обновляем timestamp
			$this->conversationModel->update($conversationId, [
				'updated_at' => date('Y-m-d H:i:s')
			]);

			// Уведомления
			if ($messageId > 0 && $this->notificationService !== null) {
				$recipientId = $this->conversationModel->getRecipientId($conversationId, $senderId);
				if ($recipientId > 0) {
					$this->notificationService->notifyMessageSent($messageId, $recipientId, $senderId);
				}
			}

			return (int)$messageId;
		} catch (\Throwable $e) {
			Session::setFlash('error', 'Ошибка при отправке сообщения');
			return null;
		}
	}

    /**
     * Получить пагинированную историю сообщений диалога.
     * @return array Массив с сообщениями и данными пагинации
     */
    public function getPaginatedMessages(int $conversationId, int $currentPage = 1, int $perPage = 15): array
    {
        // Нормализация номера страницы
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        // Подсчёт общего количества сообщений
        $totalMessages = $this->messageModel->getTotalMessageCount($conversationId);
        $totalPages = (int)ceil($totalMessages / $perPage);

        if ($totalPages < 1) {
            $totalPages = 1;
        }

        // Вычисляем offset
        $offset = ($currentPage - 1) * $perPage;

        // Получаем сообщения
        $messages = $this->messageModel->getPaginatedChatHistory($conversationId, $perPage, $offset);

        return [
            'messages' => $messages,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalMessages' => $totalMessages,
        ];
    }

    /**
     * Пометить все сообщения в диалоге как прочитанные.
     */
    public function markAsRead(int $conversationId, int $userId): void
    {
        $this->messageModel->markAsRead($conversationId, $userId);
    }

    /**
     * Получить историю сообщений без пагинации (для API или AJAX).
     */
    public function getChatHistory(int $conversationId, int $limit = 100): array
    {
        return $this->messageModel->getChatHistory($conversationId);
    }
}