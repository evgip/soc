<?php
/**
 * Страница историй с тегами из конкретной категории
 * 
 * @var array $category       Данные категории (id, name, slug, description, stories)
 * @var array $stories        Список историй
 * @var int $currentPage      Текущая страница пагинации
 * @var int $totalPages       Общее количество страниц
 * @var array $newCommentsMap Карта новых комментариев для каждой истории
 * @var int $currentUserId    ID текущего пользователя (0 = гость)
 * @var bool $isAdmin         Флаг администратора
 * @var bool $canUserDownvote Может ли пользователь голосовать против
 * @var array $currentVotes   Массив голосов: story_id => vote_value
 */

// ✅ Все данные приходят из контроллера, не создаём модели здесь
$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$canUserDownvote = $canUserDownvote ?? false;
$currentVotes = $currentVotes ?? [];

// Базовый URL для пагинации (сохраняем slug категории)
$paginationBaseUrl = route('categories.show', ['slug' => $category['slug']]);
?>

<h1><?= e($title) ?></h1>

<p class="hint">
    Истории, помеченные тегами в категориях информатики:  <b><?= e($title) ?></b>

    <?php if (!empty($category['description'])): ?>
        <br><?= e($category['description']) ?>
    <?php endif; ?>
</p>

<?php if (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php 
                $isStoryDeleted = !empty($story['deleted_at']); 
                
                $fullHtml = markdown_comment($story['description'] ?? '');
                $needsTruncation = needsTruncation($fullHtml, 300);
                
                $newCount = $newCommentsMap[$story['id']] ?? 0;
            ?>

            <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

                <!-- Голосование -->
                <?php partial('Votes::_voters', [
                    'type' => 'story',
                    'id' => (int)$story['id'],
                    'score' => (int)$story['score'],
                    // ✅ Используем переданный голос вместо создания модели
                    'currentVoteState' => $currentVotes[$story['id']] ?? null,
                    'canDownvote' => $canUserDownvote,
                    'isLoggedIn' => $currentUserId > 0,
                    'contentOwnerId' => (int)$story['user_id']
                ]); ?>

                <div class="story_liner">
                    <div class="link">
                        <?php if ($isStoryDeleted): ?>
                            <em>[Удалена модератором]</em>
                        <?php endif; ?>

                        <?php
                        $isExternal = !empty($story['url']);
                        $targetUrl = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
                        ?>

                        <a href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= e($story['title']) ?>
                        </a>

                        <?php if ($isExternal): ?>
                            <?php 
                            $domainHost = !empty($story['url']) ? parse_url($story['url'], PHP_URL_HOST) : null;
                            if ($domainHost): 
                            ?>
                                <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>" class="domain">
                                    <?= e($domainHost) ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($story['tags'])): ?>  
                            <span class="tags">
                                <?php foreach ($story['tags_with_names'] as $tagData): ?>  
                                    <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag"><?= e($tagData['name']) ?></a>
                                <?php endforeach; ?>
                            </span> 
                        <?php endif; ?>
                    </div>

                        <?php if ($needsTruncation): ?>
                            <details>
                                <summary>
                                    <?= truncateDescription($fullHtml, 300) ?>
                                </summary>
                                <div class="summary-content">
                                    <?= $fullHtml ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <?= $fullHtml ?>
                        <?php endif; ?>
                  

                    <!-- Метаданные -->
                    <?php partial('Stories::_story_meta', [
                        'story' => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin,
                        'newCount' => $newCount,
                    ]); ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>

<?php else: ?>
    <p class="hint">В этой категории пока нет публикаций.</p>
<?php endif; ?>