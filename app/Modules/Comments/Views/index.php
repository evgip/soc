<?php
declare(strict_types=1);

/** 
 * @var int $currentUserId
 * @var bool $isAdmin
 * @var bool $isModerator
 * @var bool $canDownvote
 * @var array $currentCommentVotes
 * @var string|null $lastReadAt (timestamp для вычисления "новых")
 * @var array $comments
 */

// Создаём единый контекст рендеринга ОДИН раз на всю страницу.
// Это избавляет нас от передачи 6-7 переменных в каждый partial.
$commentContext = new \App\Modules\Comments\ViewModels\CommentRenderContext(
    currentUserId: $currentUserId,
    isAdmin: $isAdmin,
    isModerator: $isModerator,
    canDownvote: $canDownvote,
    // lastReadCommentId, commentsTree, renderTree не нужны в плоской ленте
);
?>

<div class="container">
    <h1>Последние комментарии</h1>
    
    <?php if (empty($comments)): ?>
        <p class="hint">Пока нет комментариев.</p>
    <?php else: ?>
        <ol class="comments comments-flat">
            <?php 
            $dividerShown = false;
            foreach ($comments as $comment): 
                // Логика определения "нового" комментария остаётся здесь,
                // так как она специфична для плоской ленты (использует timestamp).
                $isNew = $lastReadAt && strtotime($comment['created_at']) > strtotime($lastReadAt);
                $commentId = (int)$comment['id'];
                $currentVote = $currentCommentVotes[$commentId] ?? null;
            ?>
                
                <?php if ($isNew && !$dividerShown): ?>
                    <li class="new-comments-divider">
                        <hr>
                        <span>↑ Новые комментарии ↓</span>
                        <hr>
                    </li>
                    <?php $dividerShown = true; ?>
                <?php endif; ?>
                
                <?php partial('Comments::_item', [
                    'comment' => $comment,
                    'context' => $commentContext,       // ✅ Единый объект вместо 6 переменных
                    'currentVote' => $currentVote,
                    'showStoryContext' => true,         // В плоской ленте показываем ссылку на историю
                    'showCollapseToggle' => false,      // В плоской ленте нет вложенности
                    'isNewParam' => $isNew,             // Передаём вычисленное значение в _item
                ]); ?>
                
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
