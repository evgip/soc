<?php
/**
 * @var string $activeTab
 * @var int    $currentUserId
 * @var bool   $isAdmin
 * @var bool   $isModerator
 * @var string $currentUsername
 * @var array  $stories
 * @var array  $comments
 * @var array  $collections
 * @var int    $currentPage
 * @var int    $totalPages
 * @var array  $newCommentsMap
 * @var array  $currentVotes
 * @var array  $savedIds
 * @var \App\Modules\Comments\ViewModels\CommentRenderContext $commentContext
 */
$baseUrl = '/me/library';
$stories = $stories ?? [];
$comments = $comments ?? [];
$savedIds = $savedIds ?? [];
$collections = $collections ?? [];
?>
<h1>📚 Библиотека</h1>

<nav class="nav br-none" aria-label="Библиотека">
    <a href="<?= $baseUrl ?>" class="<?= $activeTab === 'saved' ? 'is-active' : '' ?>">Сохранённое</a>
    <a href="<?= $baseUrl ?>?tab=responses" class="<?= $activeTab === 'responses' ? 'is-active' : '' ?>">Ответы</a>
    <a href="<?= $baseUrl ?>?tab=history" class="<?= $activeTab === 'history' ? 'is-active' : '' ?>">История чтения</a>
    <a href="<?= $baseUrl ?>?tab=collections" class="<?= $activeTab === 'collections' ? 'is-active' : '' ?>">Коллекции</a>
</nav>

<?php if ($activeTab === 'saved'): ?>

    <?php if (empty($stories)): ?>
        <p class="hint">У вас пока нет сохранённых историй. Нажмите 🔖 на любой истории, чтобы добавить её в закладки.</p>
    <?php else: ?>
        <ol class="stories library-stories">
            <?php foreach ($stories as $story): ?>
                <?php partial('Stories::_story_row', [
                    'story'         => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin'       => $isAdmin,
                    'currentVotes'  => $currentVotes ?? [],
                    'newCommentsMap'=> $newCommentsMap ?? [],
                    'hideAuthor'    => false,
                    'isSavedPage'   => true,
                    'savedIds'      => $savedIds,
                ]); ?>
            <?php endforeach; ?>
        </ol>

        <?php if (isset($totalPages) && $totalPages > 1): ?>
            <?= pagination($currentPage, $totalPages) ?>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($activeTab === 'responses'): ?>

    <?php if (empty($comments)): ?>
        <p class="hint">Вы ещё не оставили ни одного комментария.</p>
    <?php else: ?>
        <ol class="comments comments-flat library-comments">
            <?php foreach ($comments as $comment):
                $commentId = (int)$comment['id'];
            ?>
                <?php partial('Comments::_item', [
                    'comment' => $comment,
                    'context' => $commentContext,
                    'currentVote' => null,
                    'showStoryContext' => true,
                    'showCollapseToggle' => false,
                    'isNewParam' => false,
                ]); ?>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

<?php elseif ($activeTab === 'history'): ?>

    <?php if (empty($stories)): ?>
        <p class="hint">История чтения пуста.</p>
    <?php else: ?>
        <ol class="stories library-stories">
            <?php foreach ($stories as $story): ?>
                <?php partial('Stories::_story_row', [
                    'story'         => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin'       => $isAdmin,
                    'currentVotes'  => [],
                    'newCommentsMap'=> [],
                    'hideAuthor'    => false,
                ]); ?>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

<?php elseif ($activeTab === 'collections'): ?>

    <p>
        <a href="<?= route('collections.create') ?>" class="btn btn--primary">+ Новая коллекция</a>
    </p>

    <?php if (empty($collections)): ?>
        <p class="hint">У вас пока нет коллекций. Создайте первую — соберите свои статьи по теме.</p>
    <?php else: ?>
        <div class="collections-grid">
            <?php foreach ($collections as $collection): ?>
                <?php partial('Collections::_card', [
                    'collection'  => $collection,
                    'profileUser' => ['username' => $currentUsername],
                    'isOwner'     => true,
                ]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>