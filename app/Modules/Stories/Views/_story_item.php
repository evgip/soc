<?php
/**
 * Карточка статьи для ленты (главная, профиль, теги, поиск, закладки, категории)
 * 
 * Единый горизонтальный формат (Medium-style).
 * 
 * @var array $story              - данные статьи
 * @var int   $currentUserId      - ID текущего пользователя (0 для гостя)
 * @var bool  $isAdmin            - является ли пользователь админом
 * @var array $currentVotes       - массив хлопков пользователя [story_id => claps]
 * @var array $newCommentsMap     - количество новых комментариев [story_id => count]
 * @var bool  $hideAuthor         - скрывать аватар и имя автора (в профиле)
 * @var bool  $isSavedPage        - показывать кнопку "убрать из закладок" (страница /saved)
 * @var bool  $isExternal         - статья-ссылка (внешний URL, домен-бейдж)
 * @var float|null $relevance     - релевантность в поиске (опционально)
 */

// Защита от undefined переменных
$currentUserId    = $currentUserId ?? 0;
$isAdmin          = $isAdmin ?? false;
$currentVotes     = $currentVotes ?? [];
$newCommentsMap   = $newCommentsMap ?? [];
$hideAuthor       = $hideAuthor ?? false;
$isSavedPage      = $isSavedPage ?? false;
$relevance        = $relevance ?? null;

$isStoryDeleted = !empty($story['deleted_at']);
$newCount       = $newCommentsMap[$story['id']] ?? 0;
$excerptHtml    = get_story_excerpt($story, 2); // 2 абзаца для ленты
$firstImage     = get_story_first_image($story, 'medium');

// Статья-ссылка: заголовок ведёт на внешний URL
$isExternal = !empty($story['url']);
$targetUrl  = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
$externalAttrs = $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '';
$domainHost = $isExternal ? parse_url($story['url'], PHP_URL_HOST) : null;
?>

<li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">
    
    <div class="story_liner">
        <div class="story-card-body">
            <!-- Заголовок и теги -->
            <div class="link">
                <?php if ($isStoryDeleted): ?>
                    <em>[Удалена модератором]</em>
                <?php endif; ?>

                <a class="title" href="<?= $targetUrl ?>" <?= $externalAttrs ?>>
                    <?php if (!empty($story['is_staff_pick'])): ?>
                        <span class="staff-pick-badge" title="Выбор редакции">⭐</span>
                    <?php endif; ?>
                    <span class="title-text">
                        <?= e($story['title']) ?>
                        <?php if (!empty($story['has_paywall'])): ?>
                            <span class="paywall-badge" title="Часть статьи доступна только участникам">🔒</span>
                        <?php endif; ?>
                    </span>
                </a>

                <?php if ($domainHost): ?>
                    <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>" class="domain">
                        <?= e($domainHost) ?>
                    </a>
                <?php endif; ?>

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
                'isSavedPage'   => $isSavedPage,
                'relevance'     => $relevance,
            ]); ?>
        </div>

        <!-- Картинка-превью (если есть) -->
        <?php if ($firstImage && !$isStoryDeleted): ?>
            <a href="<?= $targetUrl ?>" <?= $externalAttrs ?> class="story-thumbnail">
                <img src="<?= e($firstImage) ?>" alt="" loading="lazy">
            </a>
        <?php endif; ?>
    </div>
</li>
