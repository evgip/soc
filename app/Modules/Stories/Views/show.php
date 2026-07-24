<?php
declare(strict_types=1);

/** 
 * @var App\Modules\Stories\ViewModels\StoryShowViewModel $viewModel 
 */

$isStoryDeleted = !empty($viewModel->story['deleted_at']);
?>

<!-- КАРТОЧКА ПУБЛИКАЦИИ -->
<ol class="stories">
    <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

        <!-- Голосование -->
        <?php partial('Votes::_voters', [
            'type' => 'story',
            'id' => (int)$viewModel->story['id'],
            'score' => (int)$viewModel->story['score'],
            'currentVoteState' => $viewModel->currentStoryVote,
            'canDownvote' => $viewModel->canUserDownvote,
            'isLoggedIn' => $viewModel->currentUserId > 0,
            'contentOwnerId' => $viewModel->isAuthor
        ]); ?>

        <!-- Контент публикации -->
        <div class="story_liner">

            <!-- Заголовок и ссылка -->
            <div class="link">
                <?php if ($isStoryDeleted): ?>
                    <em>[Удалена модератором]</em>
                <?php endif; ?>

                <?php
                $isExternal = !empty($viewModel->story['url']);
                $targetUrl = $isExternal ? e($viewModel->story['url']) : route('story.show', ['id' => $viewModel->story['id']]);
                ?>

                <a href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?= e($viewModel->story['title']) ?>
                </a>

                <?php if ($isExternal): ?>
                    <?php
                    $domainHost = parse_url($viewModel->story['url'], PHP_URL_HOST);
                    if ($domainHost):
                    ?>
                        <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>" class="domain">
                            <?= e($domainHost) ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($viewModel->story['tags_with_names'])): ?>
                <span class="tags">
                    <?php foreach ($viewModel->story['tags_with_names'] as $tagData): ?>
                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag tag-<?= e($tagData['slug']); ?>">
                            <?= e($tagData['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </span>

                <!-- ✅ Кнопка "Предложить правку": вся логика спрятана в метод ViewModel -->
                <?php if ($viewModel->canShowSuggestButton()): ?>
                    <?php partial('Suggestions::suggest_button', ['story' => $viewModel->story]) ?>
                <?php elseif ($viewModel->hasReachedSuggestLimit()): ?>
                    <p class="hint">Вы уже отправили максимальное количество предложений.</p>
                <?php endif; ?>

                <!-- Блок активных предложений -->
                <?php if ($viewModel->currentUserId > 0 && !empty($viewModel->activeSuggestions)): ?>
                    <?php partial('Suggestions::active_suggestions', ['activeSuggestions' => $viewModel->activeSuggestions]) ?>
                <?php endif; ?>

                <!-- Блок истории изменений и модалка -->
                <?php if ($viewModel->currentUserId > 0): ?>
                    <?php if (!empty($viewModel->changeLog)): ?>
                        <?php partial('Suggestions::change_log', ['changeLog' => $viewModel->changeLog]) ?>
                    <?php endif; ?>

                    <?php partial('Suggestions::suggest_modal', [
                        'allTags' => $viewModel->allTags,
                        'currentTagIds' => $viewModel->currentTagIds
                    ]) ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Описание -->
            <?php if (!empty($viewModel->story['description'])): ?>
                <div class="story_content">
                    <?= markdown($viewModel->story['description']) ?>
                </div>
            <?php endif; ?>

            <!-- Метаданные (byline) -->
            <div class="byline">
                <?php if (!empty($viewModel->story['author_avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($viewModel->story['author_avatar'], 0, 2) ?>/<?= e($viewModel->story['author_avatar']) ?>" class="avatar" alt="">
                <?php endif; ?>

                <a href="<?= route('user.profile', ['username' => $viewModel->story['author_name']]) ?>" <?= $viewModel->isAuthor ? 'class="user_is_author"' : '' ?>>
                    <?= e($viewModel->story['author_name']) ?>
                </a>

                <span class="divider">|</span>
                <span title="<?= e(date('d.m.Y H:i:s', strtotime($viewModel->story['created_at']))) ?>">
                    <?= e(date('d.m.Y H:i', strtotime($viewModel->story['created_at']))) ?>
                </span>

                <span class="divider">|</span>
                <a href="<?= route('story.show', ['id' => $viewModel->story['id']]) ?>#comments">
                    <?php $commentsCount = (int)$viewModel->story['comments_count']; ?>
                    <?= $commentsCount === 0 ? 'обсудить' : $commentsCount . ' ' . plural($commentsCount, ['комментарий', 'комментария', 'комментариев']) ?>
                </a>

                <!-- Редактирование -->
                <?php if ($viewModel->currentUserId > 0 && ($viewModel->isAuthor || $viewModel->isAdmin) && !$isStoryDeleted): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('story.edit', ['id' => $viewModel->story['id']]) ?>">edit</a>
                <?php endif; ?>

                <!-- Жалоба -->
                <?php if ($viewModel->currentUserId > 0): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$viewModel->story['id']]) ?>"
                        class="flag-link"
                        title="Пожаловаться на контент"
                        data-confirm="Вы уверены, что хотите подать жалобу?">
                        🚩
                    </a>
                <?php endif; ?>

                <!-- Админские действия -->
                <?php if ($viewModel->isAdmin): ?>
                    <span class="divider">|</span>
                    <?php if ($isStoryDeleted): ?>
                        <form action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/restore" method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link">восстановить</button>
                        </form>
                    <?php else: ?>
                        <form action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/delete" method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link red">удалить</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="story_btn">
                    <?php if ($viewModel->isAuthor): ?>  
                        <form method="POST" action="/story/<?= $viewModel->story['id'] ?>/follow" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm <?= $viewModel->story['user_is_following'] ? 'btn-danger' : 'btn-outline-primary' ?>">
                                <?= $viewModel->story['user_is_following'] ? '🔔 Вы подписаны' : '🔕 Подписаться на ответы' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($viewModel->currentUserId > 0): ?>
                        <form method="POST" action="/saved/toggle/<?= (int)$viewModel->story['id'] ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn <?= $viewModel->isStorySaved ? 'btn-danger' : '' ?>">
                                <?= $viewModel->isStorySaved ? '🔖 В закладках' : '🔖 В закладки' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- ✅ Кнопка "Отметить прочитанным": логика в методе ViewModel -->
                <?php if ($viewModel->canMarkAsRead()): ?> 
                    <br><br>
                    <form action="/story/<?= (int)$viewModel->story['id'] ?>/mark-read" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Сбросить счётчик новых комментариев">
                            ✓ Отметить прочитанным
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </li>
</ol>

<!-- ФОРМА КОРНЕВОГО КОММЕНТАРИЯ -->
<div class="comment_form_container" id="comment-form-container">
    <?php if ($viewModel->currentUserId > 0 && !$isStoryDeleted): ?>
        <h3>Оставить комментарий</h3>
        <form action="/comments/create" method="POST" id="main-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="story_id" value="<?= (int)$viewModel->story['id'] ?>">
            <input type="hidden" name="parent_id" id="form-parent-id" value="">

            <textarea name="comment_text" id="form-comment-textarea" required
                placeholder="Ваш комментарий... (поддерживается Markdown)"></textarea>

            <button type="submit">Опубликовать комментарий</button>
            <button type="button" id="btn-cancel-reply">Отмена</button>
        </form>
    <?php else: ?>
        <p class="hint">
            Вы должны <a href="/login">войти в аккаунт</a>, чтобы принимать участие в обсуждениях.
        </p>
    <?php endif; ?>
</div>

<hr>

<!-- КОММЕНТАРИИ -->
<div class="comment-head">
    <h3 id="comments">Комментарии (<?= (int)$viewModel->story['comments_count'] ?>)</h3>
    <?php if (!empty($viewModel->commentsTree)): ?>
        <button type="button" id="collapse-all-comments" class="btn btn-sm btn-outline-secondary">
            Свернуть все ветки
        </button>
    <?php endif; ?>
</div>

<?php if (empty($viewModel->commentsTree)): ?>
    <p class="hint">Здесь пока нет комментариев. Будьте первым!</p>
<?php else: ?>
	<?php
		$renderTree = function (int $parentId, int $depth = 1) use (&$renderTree, $viewModel) {
			if (!isset($viewModel->commentsTree[$parentId])) {
				return;
			}

			// Ограничиваем визуальную глубину (например, максимум 6 уровней)
			$maxDepth = 6;
			$visualDepth = min($depth, $maxDepth);

			// Создаем контекст для текущего уровня
			$context = new \App\Modules\Comments\ViewModels\CommentRenderContext(
				currentUserId: $viewModel->currentUserId,
				isAdmin: $viewModel->isAdmin,
				isModerator: $viewModel->isModerator,
				canDownvote: $viewModel->canUserDownvote,
				lastReadCommentId: $viewModel->lastReadCommentId,
				commentsTree: $viewModel->commentsTree,
				renderTree: $renderTree,
			);
		?>
			<ol class="comments">
				<?php foreach ($viewModel->commentsTree[$parentId] as $comment): ?>
					<?php
					$commentId = (int)$comment['id'];
					$currentCommentVote = $viewModel->currentCommentVotes[$commentId] ?? null;
					?>
					
					<?php partial('Comments::_item', [
						'comment' => $comment,
						'context' => $context,
						'currentVote' => $currentCommentVote,
						'showStoryContext' => false,
						'showCollapseToggle' => true,
						'depth' => $visualDepth,
					]); ?>
				<?php endforeach; ?>
			</ol>
		<?php
		};

		// Запускаем рекурсию с глубины 1
		$renderTree(0, 1);
	?>
<?php endif; ?>