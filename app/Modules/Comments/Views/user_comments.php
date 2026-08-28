<?php
declare(strict_types=1);

$commentContext = new \App\Modules\Comments\ViewModels\CommentRenderContext(
    currentUserId: $currentUserId,
    isAdmin: $isAdmin,
    isModerator: $isModerator,
);
?>

<div class="container">
    <h1>Комментарии пользователя <?= e($profileUser['username']) ?></h1>
    
    <?php if (empty($comments)): ?>
        <p class="hint">Пользователь ещё не оставил комментариев.</p>
    <?php else: ?>
        <ol class="comments comments-flat">
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
</div>