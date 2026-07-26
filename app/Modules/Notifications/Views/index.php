<?php

/** @var array $notifications */
/** @var string $currentType */
/** @var array $counts */
/** @var int $totalUnread */

?>

<div class="notifications-page">

    <div class="notifications-header">
        <h2>Уведомления</h2>
        <?php if ($totalUnread > 0): ?>
            <button class="is-link" id="mark-all-read-btn">Отметить все как прочитанные</button>
        <?php endif; ?>
    </div>

    <!-- Панель фильтров -->
    <nav>
        <a href="/notifications?type=all"
            class="<?= $currentType === 'all' ? 'active' : '' ?>">
            Все
            <?php if ($totalUnread > 0): ?>
                <span class="nav-notification-badge"><?= $totalUnread ?></span>
            <?php endif; ?>
        </a>

        <a href="/notifications?type=reply"
            class="<?= $currentType === 'reply' ? 'active' : '' ?>">
            💬 Ответы
            <?php if ($counts['reply'] > 0): ?>
                <span class="nav-notification-badge"><?= $counts['reply'] ?></span>
            <?php endif; ?>
        </a>

        <a href="/notifications?type=mention"
            class="<?= $currentType === 'mention' ? 'active' : '' ?>">
            @ Упоминания
            <?php if ($counts['mention'] > 0): ?>
                <span class="nav-notification-badge"><?= $counts['mention'] ?></span>
            <?php endif; ?>
        </a>

        <a href="/notifications?type=message"
            class="<?= $currentType === 'message' ? 'active' : '' ?>">
            ✉️ Сообщения
            <?php if ($counts['message'] > 0): ?>
                <span class="nav-notification-badge"><?= $counts['message'] ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <!-- Список уведомлений -->
    <?php if (empty($notifications)): ?>

        <div class="flash-notice">
            У вас нет уведомлений <?= $currentType !== 'all' ? 'этого типа' : '' ?>.
        </div>

    <?php else: ?>

        <ol class="notification-list">
            <?php foreach ($notifications as $notif): ?>
                <?php
                // Определяем иконку и текст в зависимости от типа
                $icon = '🔔';
                $actionText = '';
                $link = '/notifications';

                if ($notif['type'] === 'reply') {
                    $icon = '💬';
                    $actionText = 'ответил на ваш комментарий';
                    $link = '/story/' . $notif['story_id'] . '#comment-block-' . $notif['notifiable_id']; // /story/2#comment-block-7
                } elseif ($notif['type'] === 'story_comment') {
                    $icon = '💬';
                    $actionText = 'ответил на ваш пост';
                    $link = '/story/' . $notif['story_id'] . '#comment-block-' . $notif['notifiable_id'];
                } elseif ($notif['type'] === 'mention') {
                    $icon = '@';
                    $actionText = 'упомянул вас в комментарии';
                    $link = '/story/' . $notif['story_id'] . '#comment-block-' . $notif['notifiable_id'];
                } elseif ($notif['type'] === 'message') {
                    $icon = '✉️';
                    $actionText = 'отправил вам сообщение';
                    $link = '/messages/chat/' . ($notif['conversation_id'] ?? $notif['source_id']);
                }
                ?>
                <li class="notification-item <?= $notif['is_read'] ? '' : 'notification-unread' ?>">
                    <a href="<?= $link ?>"
                        class="notification-link"
                        data-notification-id="<?= $notif['id'] ?>">

                        <div class="notification-icon">
                            <?= $icon ?>
                        </div>

                        <div class="notification-body">
                            <span class="notification-sender">
                                <?= htmlspecialchars($notif['sender_name'] ?? 'Пользователь') ?>
                            </span>
                            <span class="notification-action"><?= $actionText ?></span>

                            <?php if (!empty($notif['content_text'])): ?>
                                <blockquote class="notification-quote">
                                    <?= htmlspecialchars(mb_substr($notif['content_text'], 0, 100)) ?>...
                                </blockquote>
                            <?php endif; ?>

                            <div class="byline">
                                <span class="notification-time">
                                    <?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>

    <?php endif; ?>

</div>