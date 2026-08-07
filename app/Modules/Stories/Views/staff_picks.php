<?php
/**
 * Страница "Выбор редакции"
 * 
 * @var array $stories
 * @var int $currentPage
 * @var int $totalPages
 * @var int $totalCount
 */
?>

<div class="staff-picks-page">
    <!-- Шапка страницы -->
    <header class="staff-picks-page__header">
        <h1 class="staff-picks-page__title">⭐ Выбор редакции</h1>
        <p class="staff-picks-page__subtitle">
            Статьи, отобранные нашей командой за их глубину, оригинальность и ценность для читателей.
        </p>
        <?php if ($totalCount > 0): ?>
            <p class="staff-picks-page__count">
                Всего публикаций: <?= $totalCount ?>
            </p>
        <?php endif; ?>
    </header>

    <!-- Сетка статей -->
    <?php if (!empty($stories)): ?>
        <div class="staff-picks-page__grid">
            <?php foreach ($stories as $story): ?>
                <article class="staff-pick-card">
                    <?php $firstImage = get_story_first_image($story); ?>
                    
                    <?php if ($firstImage): ?>
                        <a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="staff-pick-card__image">
                            <img src="<?= e($firstImage) ?>" alt="" loading="lazy">
                        </a>
                    <?php endif; ?>
                    
                    <div class="staff-pick-card__content">
                        <!-- Автор и дата -->
                        <div class="staff-pick-card__meta">
                            <?php if (!empty($story['author_avatar'])): ?>
                                <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" 
                                     class="staff-pick-card__avatar" alt="">
                            <?php endif; ?>
                            <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" 
                               class="staff-pick-card__author">
                                <?= e($story['author_name']) ?>
                            </a>
                            <span class="staff-pick-card__divider">·</span>
                            <span class="staff-pick-card__date">
                                <?= format_date_ru($story['created_at'], 'long') ?>
                            </span>
                        </div>
                        
                        <!-- Заголовок -->
                        <h2 class="staff-pick-card__title">
                            <a href="<?= route('story.show', ['id' => $story['id']]) ?>">
                                <?= e($story['title']) ?>
                            </a>
                        </h2>
                        
                        <!-- Краткое содержание -->
                        <?php $excerpt = get_story_excerpt($story, 2); ?>
                        <?php if ($excerpt): ?>
                            <div class="staff-pick-card__excerpt">
                                <?= $excerpt ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Теги -->
                        <?php if (!empty($story['tags_with_names'])): ?>
                            <div class="staff-pick-card__tags">
                                <?php foreach ($story['tags_with_names'] as $tag): ?>
                                    <a href="<?= route('tags.filter', ['tagslug' => e($tag['slug'])]) ?>" 
                                       class="tag tag-<?= e($tag['slug']) ?>">
                                        <?= e($tag['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Футер карточки -->
                        <div class="staff-pick-card__footer">
                            <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments" 
                               class="staff-pick-card__comments">
                                💬 <?= (int)$story['comments_count'] ?>
                            </a>
                            <span class="staff-pick-card__score">
                                ⭐ <?= (int)$story['score'] ?>
                            </span>
                            <?php $pickedAt = $story['picked_at'] ?? null; ?>
                            <?php if ($pickedAt): ?>
                                <span class="staff-pick-card__picked" 
                                      title="Дата добавления в выбор редакции">
                                    ⭐ <?= format_date_ru($pickedAt) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Пагинация -->
        <?php if ($totalPages > 1): ?>
            <?= pagination($currentPage, $totalPages) ?>
        <?php endif; ?>
    <?php else: ?>
        <!-- Пустое состояние -->
        <div class="empty-state">
            <h2>Пока нет статей в выборе редакции</h2>
            <p>
                Наши кураторы ещё не выбрали лучшие публикации. 
                Загляните позже или опубликуйте свою статью — возможно, именно она станет первой!
            </p>
            <?php if ($currentUserId > 0): ?>
                <div class="empty-state__actions">
                    <a href="<?= route('story.form') ?>" class="btn btn-pill btn-accent">
                        Написать статью
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>