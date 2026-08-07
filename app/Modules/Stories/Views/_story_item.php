<?php
/**
 * Карточка статьи для ленты (главная, профиль, теги)
 * 
 * @var array $story              - данные статьи
 * @var int   $currentUserId      - ID текущего пользователя (0 для гостя)
 * @var bool  $isAdmin            - является ли пользователь админом
 * @var bool  $canUserDownvote    - может ли пользователь голосовать против
 * @var array $currentVotes       - массив голосов пользователя [story_id => vote]
 * @var array $newCommentsMap     - количество новых комментариев [story_id => count]
 * @var bool  $hideAuthor         - скрывать аватар и имя автора (в профиле)
 */

// Защита от undefined переменных
$currentUserId    = $currentUserId ?? 0;
$isAdmin          = $isAdmin ?? false;
$canUserDownvote  = $canUserDownvote ?? false;
$currentVotes     = $currentVotes ?? [];
$newCommentsMap   = $newCommentsMap ?? [];
$hideAuthor       = $hideAuthor ?? false;

$isStoryDeleted = !empty($story['deleted_at']);
$newCount       = $newCommentsMap[$story['id']] ?? 0;
$excerptHtml    = get_story_excerpt($story, 2); // 2 абзаца для ленты
$firstImage     = get_story_first_image($story);
?>

<li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">
    
    <!-- Голосование -->
    <?php partial('Votes::_voters', [
        'type'           => 'story',
        'id'             => (int)$story['id'],
        'score'          => (int)($story['score'] ?? 0),
        'currentVoteState' => $currentVotes[$story['id']] ?? null,
        'canDownvote'    => $canUserDownvote,
        'isLoggedIn'     => $currentUserId > 0,
        'contentOwnerId' => (int)($story['user_id'] ?? 0),
    ]); ?>

    <div class="story_liner">
        <!-- Заголовок и теги -->
        <div class="link">
            <?php if ($isStoryDeleted): ?>
                <em>[Удалена модератором]</em>
            <?php endif; ?>

            <?php if (!empty($story['is_staff_pick'])): ?>
                <span class="staff-pick-badge" title="Выбор редакции">⭐</span>
            <?php endif; ?>

 

            <a class="title" href="<?= route('story.show', ['id' => $story['id']]) ?>">
                <?= e($story['title']) ?>
				<?php if (!empty($story['has_paywall'])): ?>
					<span class="paywall-badge" title="Часть статьи доступна только участникам">🔒</span>
				<?php endif; ?>
            </a>

            <?php if (!empty($story['tags_with_names'])): ?>
                <span class="tags">
                    <?php foreach ($story['tags_with_names'] as $tagData): ?>
                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" 
                           class="tag tag-<?= e($tagData['slug']); ?>">
                            <?= e($tagData['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Картинка-превью (если есть) -->
        <?php if ($firstImage && !$isStoryDeleted): ?>
            <a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="story-thumbnail">
                <img src="<?= e($firstImage) ?>" alt="" loading="lazy">
            </a>
        <?php endif; ?>

        <!-- Краткое содержание -->
        <?php if ($excerptHtml && !$isStoryDeleted): ?>
            <div class="story_content excerpt">
                <?= $excerptHtml ?>
            </div>
        <?php endif; ?>

        <!-- Метаданные (автор, дата, комментарии, действия) -->
        <?php partial('Stories::_story_meta', [
            'story'         => $story,
            'currentUserId' => $currentUserId,
            'isAdmin'       => $isAdmin,
            'newCount'      => $newCount,
            'hideAuthor'    => $hideAuthor,
        ]); ?>
    </div>
</li>