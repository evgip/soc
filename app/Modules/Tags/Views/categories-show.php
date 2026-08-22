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
            <?php partial('Stories::_story_item', [
                'story'         => $story,
                'currentUserId' => $currentUserId,
                'isAdmin'       => $isAdmin,
                'canUserDownvote' => $canUserDownvote,
                'currentVotes'  => $currentVotes,
                'newCommentsMap'=> $newCommentsMap,
                'hideAuthor'    => false,
            ]); ?>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>

<?php else: ?>
    <p class="hint">В этой категории пока нет публикаций.</p>
<?php endif; ?>