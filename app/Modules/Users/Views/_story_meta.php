<?php
/**
 * @var array $story         - данные публикации
 * @var int   $currentUserId - ID текущего пользователя
 * @var bool  $isAdmin       - является ли текущий пользователь админом
 */
$isDeleted = !empty($story['deleted_at']);
?>
<div class="byline">
    <?php if (!empty($story['author_avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" class="avatar" alt="">
    <?php endif; ?>

    <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" <?= (int)$story['user_id'] === $currentUserId ? 'class="user_is_author"' : '' ?>>
        <?= e($story['author_name']) ?>
    </a>

    <span class="divider">|</span>
    <span title="<?= e(date('d.m.Y H:i:s', strtotime($story['created_at']))) ?>">
        <?= e(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
    </span>

    <span class="divider">|</span>
    <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments">
        <?php if ((int)$story['comments_count'] === 0): ?>
            обсудить
        <?php else: ?>
            <?= (int)$story['comments_count'] ?> <?= plural((int)$story['comments_count'], ['комментарий', 'комментария', 'комментариев']) ?>
			
				<?php if ($newCount > 0): ?>
                    <span class="new-comments" title="Новых комментариев с последнего посещения">
                        +<?= $newCount ?>
                    </span>
                <?php endif; ?>
			
        <?php endif; ?>
    </a>

    <?php if ($currentUserId > 0 && ((int)$story['user_id'] === $currentUserId || $isAdmin) && !$isDeleted): ?>
        <span class="divider">|</span>
        <a href="<?= route('story.edit', ['id' => $story['id']]) ?>">edit</a>
    <?php endif; ?>

	<?php if ($currentUserId > 0): ?>
	    <span class="divider">|</span>
		<a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$story['id']]) ?>"
		   class="flag-link"
		   title="Пожаловаться на контент"
		   data-confirm="Вы уверены, что хотите подать жалобу?">
			🚩
		</a>
	<?php endif; ?>

    <?php if ($isAdmin): ?>
        <span class="divider">|</span>
        <?php if ($isDeleted): ?>
            <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="is-link">восстановить</button>
            </form>
        <?php else: ?>
            <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="is-link red">удалить</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>