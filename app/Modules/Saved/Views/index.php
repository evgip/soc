<?php
// app/Modules/Saved/Views/index.php

$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$canUserDownvote = $canUserDownvote ?? false;
$currentVotes = $currentVotes ?? [];
$newCommentsMap = $newCommentsMap ?? [];
$stories = $stories ?? [];
?>

<div class="container">
    <h1>📚 Мои закладки</h1>
    
    <?php if (empty($stories)): ?>
        <p class="hint">У вас пока нет сохранённых историй. Нажмите 🔖 на любой истории, чтобы добавить её в закладки.</p>
    <?php else: ?>
        <ol class="stories">
            <?php foreach ($stories as $story): ?>
                <?php partial('Stories::_story_item', [
                    'story'         => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin'       => $isAdmin,
                    'canUserDownvote'=> $canUserDownvote,
                    'currentVotes'  => $currentVotes,
                    'newCommentsMap'=> $newCommentsMap,
                    'hideAuthor'    => false,
                    'isSavedPage'   => true,
                ]); ?>
            <?php endforeach; ?>
        </ol>
        
        <?php if (isset($totalPages) && $totalPages > 1): ?>
            <?= pagination($currentPage, $totalPages) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>