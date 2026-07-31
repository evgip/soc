<!-- Шапка диалога -->
<div class="dialog-header">
    <a href="<?= route('messages.index') ?>" class="dialog-back-link">← К списку</a>
    
    <?php if (!empty($recipient['avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($recipient['avatar'], 0, 2) ?>/<?= e($recipient['avatar']) ?>" 
             class="dialog-avatar" alt="avatar">
    <?php else: ?>
        <div class="dialog-avatar-placeholder">
            <?= e(mb_substr($recipient['username'], 0, 1)) ?>
        </div>
    <?php endif; ?>
    
    <span class="dialog-title">
        Чат с пользователем <?= e($recipient['username']) ?>
    </span>
</div>

<!-- Пагинация (загрузка старых сообщений) -->
<?php if ($totalPages > 1): ?>
    <div class="dialog-pagination">
        <?php if ($currentPage < $totalPages): ?>
            <a href="?chat_page=<?= $currentPage + 1 ?>" class="dialog-load-older">
                Загрузить более ранние сообщения
            </a>
        <?php else: ?>
            <span class="dialog-pagination-status">Вы пролистали до самого начала переписки</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Сообщения -->
<div class="dialog-messages">
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <?php $isOutgoing = ((int)$msg['sender_id'] === \W3a\Core\Auth\Auth::id()); ?>
            
            <div class="dialog-message <?= $isOutgoing ? 'outgoing' : 'incoming' ?>" 
                 title="<?= e(date('d.m.Y H:i', strtotime($msg['created_at']))) ?>">
                <div class="dialog-message-text">
                    <?= nl2br(e($msg['message'])) ?>
                </div>
                <div class="dialog-message-time">
                    <?= e(date('d.m H:i', strtotime($msg['created_at']))) ?>
                </div>
            </div>
            
        <?php endforeach; ?>
    <?php else: ?>
        <p class="dialog-empty">История сообщений пуста. Напишите что-нибудь первое!</p>
    <?php endif; ?>
</div>

<!-- Форма отправки сообщения -->
<div class="dialog-form">
    <form action="<?= route('messages.send.submit') ?>" method="POST" class="dialog-form-row">
        <?= csrf_field() ?>
        <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
        
        <input type="text" name="message_text" required autocomplete="off" 
               placeholder="Введите ваше сообщение..." class="dialog-form-input">
        
        <button type="submit" class="dialog-form-button">Отправить</button>
    </form>
</div>