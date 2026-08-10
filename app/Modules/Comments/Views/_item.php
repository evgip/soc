<?php

declare(strict_types=1);

/** 
 * @var array $comment 
 * @var App\Modules\Comments\ViewModels\CommentRenderContext $context
 * @var ?int $currentVote (Голос за этот конкретный комментарий)
 * @var bool $showStoryContext
 * @var bool $showCollapseToggle
 */

$commentId = (int)$comment['id'];
$isDeleted = !empty($comment['deleted_at']);
$isOwner = ((int)$comment['user_id'] === $context->currentUserId);

$showStoryContext = $showStoryContext ?? false;
$showCollapseToggle = $showCollapseToggle ?? true;
$depth = $depth ?? 1;

// Логика "нового" комментария
$isNew = false;
if ($context->lastReadCommentId !== null) {
    $isNew = $context->lastReadCommentId > 0 && $commentId > $context->lastReadCommentId;
} elseif (isset($isNewParam)) { // Если передан явно извне
    $isNew = (bool)$isNewParam;
}

?>

<li class="comment comment-thread depth-<?= $depth ?> <?= $isDeleted ? 'deleted' : '' ?> <?= $isNew ? 'is-new' : '' ?>" 
    data-comment-id="<?= $commentId ?>" 
    id="comment-block-<?= $commentId ?>">




    <div class="comment_body">
        <div class="comment-header">
            <?php if ($showCollapseToggle): ?>
                <span class="collapse-toggle" title="Свернуть ветку">[–]</span>
            <?php endif; ?>
            
            <?php partial('Comments::_comment_meta', [
                'comment' => $comment,
                'currentUserId' => $context->currentUserId,
                'isAdmin' => $context->isAdmin,
                'isModerator' => $context->isModerator,
            ]); ?>
            
        </div>

            <?php if ($showStoryContext && !empty($comment['story_title'])): ?>
                <div class="comment_meta">
                    <a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#comment-block-<?= $commentId ?>" 
                       class="story-context">
                        на: <?= e($comment['story_title']) ?>
                    </a>
                </div>
            <?php endif; ?>

        <?php if (!$isDeleted): ?>
            <div class="comment_text" id="comment-text-content-<?= $commentId ?>"
                 data-raw="<?= e($comment['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <?= markdown_comment($comment['comment'] ?? '') ?>
            </div>

            <div class="comment_actions">
			    <?php if (!$isDeleted): ?>
					<div class="comment_votes">
						<?php partial('Votes::_voters', [
							'type' => 'comment',
							'id' => $commentId,
							'score' => (int)$comment['score'],
							'currentVoteState' => $currentVote ?? null,
							'canDownvote' => $context->canDownvote,
							'isLoggedIn' => $context->currentUserId > 0,
							'contentOwnerId' => (int)$comment['user_id'], // Для комментариев владельца определяем внутри _voters или передаем отдельно если нужно
							'inline' => true
						]); ?>
					</div>
				<?php else: ?>
					<div class="comment_votes">
						<span class="score"><?= (int)$comment['score'] ?></span>
					</div>
				<?php endif; ?>
			
			
			
                <?php if ($context->currentUserId > 0): ?>
                    <?php if ($showStoryContext): ?>
                        <a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#reply-to-<?= $commentId ?>" 
                           class="comment-reply-link" data-id="<?= $commentId ?>">Ответить</a>
                    <?php else: ?>
                        <a href="#reply-to-<?= $commentId ?>" 
                           class="comment-reply-link" data-id="<?= $commentId ?>">Ответить</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($isOwner || $context->isAdmin || $context->isModerator): ?>
                    <span class="divider">|</span>
                    <a class="comment-edit-trigger" data-id="<?= $commentId ?>">Редактировать</a>
                    <span class="divider">|</span>
                    <form action="/comments/<?= $commentId ?>/delete" method="POST" 
                          class="inline-form js-confirm-delete" data-confirm-message="Удалить комментарий?">
                        <?= csrf_field() ?>
                        <button type="submit">Удалить</button>
                    </form>
                <?php endif; ?>

                <?php if ($context->currentUserId > 0): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('flags.report', ['type' => 'comment', 'id' => $commentId]) ?>"
                       class="flag-link" title="Пожаловаться на контент"
                       data-confirm="Вы уверены, что хотите подать жалобу?">
                        🚩
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="comment_text deleted-text">
                <em>Комментарий удален</em>
            </div>
        <?php endif; ?>
    </div>

    <!-- Рекурсия ветки (работает только если переданы commentsTree и renderTree) -->
    <?php if ($context->renderTree !== null && $context->commentsTree !== null): ?>
        <?php ($context->renderTree)($commentId, $depth + 1); ?>
    <?php endif; ?>

</li>