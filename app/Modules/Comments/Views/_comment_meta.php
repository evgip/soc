<?php
/**
 * @var array $comment       - данные комментария
 * @var int   $currentUserId - ID текущего пользователя
 * @var bool  $isAdmin       - является ли текущий пользователь админом
 * @var bool  $isModerator   - является ли текущий пользователь модератором
 */
$isDeleted = !empty($comment['deleted_at']);
$isOwner = ((int)$comment['user_id'] === $currentUserId);
$isModerator = $isModerator ?? false; // Получаем из переданных данных
?>
<div class="comment_meta">
    <?php if (!$isDeleted): ?>
        <?php if (!empty($comment['author_avatar'])): ?>
            <img src="/uploads/avatars/<?= substr($comment['author_avatar'], 0, 2) ?>/<?= e($comment['author_avatar']) ?>" class="mini-avatar-img" alt="">
        <?php endif; ?>

        <a href="<?= route('user.profile', ['username' => $comment['author_name']]) ?>" <?= $isOwner ? 'class="user_is_author"' : '' ?>>
            <?= e($comment['author_name']) ?>
        </a>

        <span class="divider">|</span>
        <span title="<?= e(date('d.m.Y H:i:s', strtotime($comment['created_at']))) ?>">
            <?= e(date('d.m.Y H:i', strtotime($comment['created_at']))) ?>
        </span>

        <?php if (!empty($comment['updated_at']) && $comment['updated_at'] !== $comment['created_at']): ?>
            <span class="hint">(изменен)</span>
        <?php endif; ?>
    <?php else: ?>
        <em>[Комментарий удален]</em>
        <?php if ($isOwner || $isAdmin || $isModerator): ?>
            <form action="/comments/<?= (int)$comment['id'] ?>/restore" method="POST" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="is-link">[Восстановить]</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>