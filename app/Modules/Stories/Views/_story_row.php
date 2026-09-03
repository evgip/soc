<?php
$currentUserId    = $currentUserId ?? 0;
$isAdmin          = $isAdmin ?? false;
$currentVotes     = $currentVotes ?? [];
$newCommentsMap   = $newCommentsMap ?? [];
$hideAuthor       = $hideAuthor ?? false;
$isSavedPage      = $isSavedPage ?? false;
$relevance        = $relevance ?? null;

$story         = $story ?? [];
$newCount      = $newCommentsMap[$story['id']] ?? 0;
$excerptHtml   = get_story_excerpt($story, 1);
$firstImage    = get_story_first_image($story, 'medium');
$isExternal    = !empty($story['url']);
$targetUrl     = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
$externalAttrs = $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '';
$isDeleted     = !empty($story['deleted_at']);

$tags = $story['tags_with_names'] ?? [];

$storyUserId  = (int)($story['user_id'] ?? 0);
$isAuthor     = $currentUserId > 0 && $storyUserId === $currentUserId;
$canManage    = $currentUserId > 0 && ($isAuthor || $isAdmin) && !$isDeleted;
$showMenu     = $canManage || ($currentUserId > 0 && !$isAuthor) || $isAdmin;
?>

<article class="tt-row <?= $isDeleted ? 'tt-row--deleted' : '' ?>">

    <?php if ($firstImage && !$isDeleted): ?>
    <a href="<?= $targetUrl ?>" <?= $externalAttrs ?> class="tt-row__img-wrap">
        <img class="tt-row__img" src="<?= e($firstImage) ?>" alt="" loading="lazy">
    </a>
    <?php endif; ?>

    <div class="tt-row__body">
        <?php if (!empty($tags)): ?>
        <div class="tt-row__tags">
            <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                <a href="<?= route('tags.filter', ['tagslug' => e($tag['slug'])]) ?>" class="tt-row__tag"><?= e($tag['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2 class="tt-row__title">
            <a href="<?= $targetUrl ?>" <?= $externalAttrs ?>>
                <?php if (!empty($story['is_staff_pick'])): ?><span class="staff-pick-badge">⭐</span><?php endif; ?>
                <?php if (!empty($story['has_paywall'])): ?><span class="paywall-badge" title="Часть статьи доступна только участникам">🔒</span><?php endif; ?>
                <?= e($story['title']) ?>
            </a>
        </h2>

        <?php if ($excerptHtml && !$isDeleted): ?>
        <div class="tt-row__excerpt"><?= $excerptHtml ?></div>
        <?php endif; ?>

        <div class="tt-row__meta">
            <?php if (!$hideAuthor): ?>
                <?php if (!empty($story['author_avatar'])): ?>
                    <img class="tt-row__avatar" src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" alt="">
                <?php endif; ?>
                <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" class="tt-row__author">
                    <?= e($story['author_name'] ?? '') ?>
                </a>
                <span class="tt-row__dot">·</span>
            <?php endif; ?>

            <span title="<?= e(date('d.m.Y H:i:s', strtotime($story['created_at'] ?? ''))) ?>">
                <?= adaptive_time($story['created_at']) ?>
            </span>

            <?php $rt = (int)($story['reading_time'] ?? 0); ?>
            <?php if ($rt > 0): ?>
                <span class="tt-row__dot">·</span>
                <span><?= $rt ?> мин на чтение</span>
            <?php endif; ?>

            <div class="tt-row__claps">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor"><path d="M7.44 2.32c.03-.1.09-.1.12 0l1.2 3.53a.29.29 0 0 0 .26.2h3.88c.11 0 .13.04.04.1L9.8 8.33a.27.27 0 0 0-.1.29l1.2 3.53c.03.1-.01.13-.1.07l-3.14-2.18a.3.3 0 0 0-.32 0L4.2 12.22c-.1.06-.14.03-.1-.07l1.2-3.53a.27.27 0 0 0-.1-.3L2.06 6.16c-.1.06-.07.12.03.12h3.89a.29.29 0 0 0 .26-.19l1.2-3.52z"></path></svg>
            </div>

            <?php if ($newCount > 0): ?>
                <span class="tt-row__dot">·</span>
                <span class="tt-row__new">+<?= $newCount ?> новых</span>
            <?php endif; ?>

            <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments" class="tt-row__comments">
                <?php $commentsCount = (int)($story['comments_count'] ?? 0); ?>
                <?php if ($commentsCount === 0): ?>
                    обсудить
                <?php else: ?>
                    <?= $commentsCount ?> <?= plural($commentsCount, ['комментарий', 'комментария', 'комментариев']) ?>
                <?php endif; ?>
            </a>

            <!-- Действия -->
            <span class="tt-row__actions">
                <?php if ($currentUserId > 0): ?>
                    <form method="POST" action="/saved/toggle/<?= (int)$story['id'] ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="icon-btn" title="Сохранить">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                            </svg>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($showMenu): ?>
                <div class="story-menu-wrapper">
                    <button type="button" class="icon-btn story-menu-trigger" aria-label="Ещё">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu" role="menu">
                        <?php if ($canManage): ?>
                            <a href="<?= route('story.edit', ['id' => $story['id']]) ?>" class="dropdown-menu__item" role="menuitem"><span>✏️ Редактировать</span></a>
                        <?php endif; ?>
                        <button type="button" class="dropdown-menu__item" role="menuitem" data-copy-link="<?= route('story.show', ['id' => $story['id']]) ?>"><span>🔗 Скопировать ссылку</span></button>
                        <?php if ($currentUserId > 0 && !$isAuthor && !$isDeleted): ?>
                            <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$story['id']]) ?>" class="dropdown-menu__item" role="menuitem" data-confirm="Подать жалобу?"><span>🚩 Пожаловаться</span></a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                            <div class="dropdown-menu__divider"></div>
                            <?php if (!$isDeleted): ?>
                                <form method="POST" action="/admin/stories/<?= (int)$story['id'] ?>/toggle-pick" class="story-menu-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-menu__item" role="menuitem"><span>⭐ <?= !empty($story['is_staff_pick']) ? 'Убрать из выбора' : 'В выбор редакции' ?></span></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isDeleted): ?>
                                <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="story-menu-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-menu__item dropdown-menu__item--success" role="menuitem" data-confirm="Восстановить?"><span>♻️ Восстановить</span></button>
                                </form>
                            <?php else: ?>
                                <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="story-menu-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-menu__item dropdown-menu__item--danger" role="menuitem" data-confirm="Удалить?"><span>🗑️ Удалить</span></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </span>
        </div>
    </div>
</article>