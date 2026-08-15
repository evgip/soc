<?php
/**
 * Мета-данные статьи в ленте (byline): автор, дата, комментарии, действия
 * 
 * @var array $story         - данные публикации
 * @var int   $currentUserId - ID текущего пользователя (0 для гостя)
 * @var bool  $isAdmin       - является ли пользователь админом
 * @var int   $newCount      - количество новых комментариев
 * @var bool  $hideAuthor    - скрывать ли автора (в профиле)
 */

// Защита от undefined переменных
$currentUserId = $currentUserId ?? 0;
$isAdmin       = $isAdmin ?? false;
$newCount      = $newCount ?? 0;
$hideAuthor    = $hideAuthor ?? false;

$isDeleted   = !empty($story['deleted_at']);
$storyUserId = (int)($story['user_id'] ?? 0);
$isAuthor    = $currentUserId > 0 && $storyUserId === $currentUserId;

// Может ли пользователь управлять статьёй (редактировать)
$canManageStory = $currentUserId > 0 && ($isAuthor || $isAdmin) && !$isDeleted;

// Нужно ли вообще показывать меню (есть хотя бы одно действие)
$showMenu = $canManageStory 
         || ($currentUserId > 0 && !$isAuthor)  // для жалобы и копирования ссылки
         || $isAdmin;                            // для админов
?>

<div class="byline">
    <?php if (!$hideAuthor && !empty($story['author_avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" 
             class="avatar" 
             alt="">
    	<?php else: ?>
			<span class="mini-avatar-placeholder"><?= e(mb_substr($story['author_name'] ?? '?', 0, 1)) ?></span>
		<?php endif; ?>
	
	

    <?php if (!$hideAuthor): ?>
        <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" 
           <?= $isAuthor ? 'class="user_is_author"' : '' ?>>
            <?= e($story['author_name']) ?>
        </a>
        <span class="divider">|</span>
    <?php endif; ?>

    <span title="<?= e(date('d.m.Y H:i:s', strtotime($story['created_at']))) ?>">
        <?= adaptive_time($story['created_at']) ?>
    </span>

    <span class="score-inline">⭐ <?= (int)($story['score'] ?? 0) ?></span>

    <span class="divider">|</span>
    
    <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments">
        <?php $commentsCount = (int)($story['comments_count'] ?? 0); ?>
        <?php if ($commentsCount === 0): ?>
            обсудить
        <?php else: ?>
            <?= $commentsCount ?> <?= plural($commentsCount, ['комментарий', 'комментария', 'комментариев']) ?>
            <?php if ($newCount > 0): ?>
                <span class="red" title="Новых комментариев с последнего посещения">
                    +<?= $newCount ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </a>

    <!-- Иконки действий -->
    <div class="icon-actions">
        
        <!-- Закладка (для всех залогиненных) -->
        <?php if ($currentUserId > 0): ?>
            <form method="POST" 
                  action="/saved/toggle/<?= (int)$story['id'] ?>" 
                  class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" 
                        class="icon-btn"
                        title="Сохранить в закладки"
                        aria-label="Сохранить в закладки">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                </button>
            </form>
        <?php endif; ?>

        <!-- DROPDOWN МЕНЮ "ТРИ ТОЧКИ" -->
        <?php if ($showMenu): ?>
            <div class="story-menu-wrapper">
                <button type="button" 
                        class="icon-btn story-menu-trigger"
                        aria-haspopup="true" 
                        aria-expanded="false"
                        aria-label="Больше действий"
                        title="Больше действий">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="1"/>
                        <circle cx="12" cy="5" r="1"/>
                        <circle cx="12" cy="19" r="1"/>
                    </svg>
                </button>

                <div class="dropdown-menu" role="menu">
                    
                    <!-- Редактировать (автор или админ) -->
                    <?php if ($canManageStory): ?>
                        <a href="<?= route('story.edit', ['id' => $story['id']]) ?>" 
                           class="dropdown-menu__item" 
                           role="menuitem">
                            <span class="story-menu-item__icon">✏️</span>
                            Редактировать
                        </a>
                    <?php endif; ?>

                    <!-- Скопировать ссылку (для всех) -->
                    <button type="button" 
                            class="dropdown-menu__item" 
                            role="menuitem"
                            data-copy-link="<?= route('story.show', ['id' => $story['id']]) ?>">
                        <span class="story-menu-item__icon">🔗</span>
                        Скопировать ссылку
                    </button>

                    <!-- Пожаловаться (для залогиненных, не автор, не удалено) -->
                    <?php if ($currentUserId > 0 && !$isAuthor && !$isDeleted): ?>
                        <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$story['id']]) ?>"
                           class="dropdown-menu__item"
                           role="menuitem"
                           data-confirm="Вы уверены, что хотите подать жалобу?">
                            <span class="story-menu-item__icon">🚩</span>
                            Пожаловаться
                        </a>
                    <?php endif; ?>

                    <!-- Админские действия -->
                    <?php if ($isAdmin): ?>
                        <div class="dropdown-menu__divider"></div>
                        
                        <!-- Staff Pick -->
                        <?php if (!$isDeleted): ?>
                            <form method="POST" 
                                  action="/admin/stories/<?= (int)$story['id'] ?>/toggle-pick" 
                                  class="story-menu-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-menu__item" role="menuitem">
                                    <span class="story-menu-item__icon">⭐</span>
                                    <?= !empty($story['is_staff_pick']) ? 'Убрать из выбора' : 'В выбор редакции' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Удалить/Восстановить -->
                        <?php if ($isDeleted): ?>
                            <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" 
                                  method="POST" 
                                  class="story-menu-form">
                                <?= csrf_field() ?>
                                <button type="submit" 
                                        class="dropdown-menu__item dropdown-menu__item--success" 
                                        role="menuitem"
                                        data-confirm="Восстановить статью?">
                                    <span class="story-menu-item__icon">♻️</span>
                                    Восстановить
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" 
                                  method="POST" 
                                  class="story-menu-form">
                                <?= csrf_field() ?>
                                <button type="submit" 
                                        class="dropdown-menu__item dropdown-menu__item--danger" 
                                        role="menuitem"
                                        data-confirm="Удалить статью?">
                                    <span class="story-menu-item__icon">🗑️</span>
                                    Удалить
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>