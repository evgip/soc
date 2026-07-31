<h1>Личные сообщения</h1>

<?php if (!empty($chats)): ?>

    <ul class="messages-list">
        <?php foreach ($chats as $chat): ?>

		<?php
		$isUnread = ((int)$chat['unread_count'] > 0 && !empty($chat['last_message']) && (int)$chat['last_sender_id'] !== \W3a\Core\Auth\Auth::id());
		?>
			
            <li>
                <a href="<?= route('messages.dialog', ['id' => $chat['id']]) ?>" 
                   class="message-item <?= $isUnread ? 'unread' : '' ?>">
                    <div class="message-item-inner">
                        
                        <!-- Аватар -->
                        <?php if (!empty($chat['participant_avatar'])): ?>
                            <img src="/uploads/avatars/<?= substr($chat['participant_avatar'], 0, 2) ?>/<?= e($chat['participant_avatar']) ?>" 
                                 class="message-avatar" alt="avatar">
                        <?php else: ?>
                            <div class="message-avatar-placeholder">
                                <?= e(mb_substr($chat['participant_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Содержимое -->
                        <div class="message-content">
                            <div class="message-username">
                                <?= e($chat['participant_name']) ?>
                            </div>
                            <div class="message-preview">
                                <?php if (!empty($chat['last_message'])): ?>
                                    <?= e($chat['last_message']) ?>
                                <?php else: ?>
                                    <span class="message-preview-empty">Нет сообщений</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Время -->
                        <div class="message-timestamp">
                            <?= e(date('d.m H:i', strtotime($chat['updated_at']))) ?>
                        </div>

                    </div>
                </a>
            </li>
            
        <?php endforeach; ?>
    </ul>

<?php else: ?>
    <p class="hint">
        Ваша папка входящих пуста. Перейдите в профиль любого пользователя системы, чтобы начать приватный диалог.
    </p>
<?php endif; ?>
