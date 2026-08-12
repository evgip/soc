<?php
declare(strict_types=1);

/** 
 * @var App\Modules\Stories\ViewModels\StoryShowViewModel $viewModel 
 */

$isStoryDeleted = !empty($viewModel->story['deleted_at']);
?>


<article 
    data-story-id="<?= (int)$viewModel->story['id'] ?>"
    data-is-author="<?= $viewModel->isAuthor ? '1' : '0' ?>">
    <div class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

 
			<div class="article-header">
 

            <h1>
                <?php if ($isStoryDeleted): ?>
                    <em>[Удалена модератором]</em>
                <?php endif; ?>
				<?php if (!empty($viewModel->story['is_staff_pick'])): ?>
					<span class="staff-pick-badge" title="Выбор редакции">⭐</span>
				<?php endif; ?>
                <?= e($viewModel->story['title']) ?>
            </h1>
			
			 
			   <div class="data">
			   <?php if (!empty($viewModel->story['author_avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($viewModel->story['author_avatar'], 0, 2) ?>/<?= e($viewModel->story['author_avatar']) ?>" class="avatar" alt="">
                <?php endif; ?>

                <a href="<?= route('user.profile', ['username' => $viewModel->story['author_name']]) ?>" <?= $viewModel->isAuthor ? 'class="user_is_author"' : '' ?>>
                    <?= e($viewModel->story['author_name']) ?>
                </a>
				 

				<?php if ($viewModel->currentUserId > 0 && !$viewModel->isAuthor): ?>
					<!-- Кнопка подписки на автора (не показываем самому автору) -->
					<form action="/subscribe/user/<?= (int)$viewModel->story['user_id']; ?>" method="POST">
						<?= csrf_field() ?>
						<button type="submit" class="btn btn-sm btn-pill <?= $isFollowing ? 'btn-secondary' : 'btn-primary' ?>">
							<?= $isFollowing ? '✓ Подписаны' : '+ Подписаться' ?>
						</button>
					</form>
				<?php endif; ?>
								
     
				<?php if ($viewModel->isAdmin && !$isStoryDeleted): ?>
					<form action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/toggle-pick" method="POST" class="inline-form">
						<?= csrf_field() ?>
						<button type="submit" 
								class="btn btn-sm btn-pill <?= !empty($viewModel->story['is_staff_pick']) ? 'btn-primary' : 'btn-outline-secondary' ?>"
								title="<?= !empty($viewModel->story['is_staff_pick']) ? 'Убрать из выбора редакции' : 'Добавить в выбор редакции' ?>">
							<?= !empty($viewModel->story['is_staff_pick']) ? '⭐ В выборе' : '⭐ В выбор редакции' ?>
						</button>
					</form>
				<?php endif; ?>
				
				
				 <span title="<?= e(date('d.m.Y H:i:s', strtotime($viewModel->story['created_at']))) ?>">
                    <?= adaptive_time($viewModel->story['created_at']) ?>
                </span>
				
				</div>
			</div>

			<?php if (!empty($viewModel->story['description_json'])): ?>
				<?php
				// === ИСПОЛЬЗУЕМ НОВОЕ СВОЙСТВО canSeeFullContent ИЗ VIEWMODEL ===
				// Это свойство уже учитывает: тип пейволла + Friend Link + подписку
				$hasAccess = $viewModel->canSeeFullContent;
				
				$rendered = render_story_with_paywall($viewModel->story, $hasAccess);
				?>
				
				<div class="story_content article-body">
					<?= $rendered['html'] ?>
				</div>
				
				<?php if ($rendered['is_locked']): ?>
					<!-- Блок-призыв вместо закрытого контента -->
					<div class="paywall-cta">
						<div class="paywall-cta__divider">
							<span class="paywall-cta__lock">🔒</span>
						</div>
						<h3 class="paywall-cta__title">
							Продолжение доступно участникам
						</h3>
						<p class="paywall-cta__text">
							<?php if ($viewModel->currentUserId === 0): ?>
								Войдите в аккаунт или зарегистрируйтесь, чтобы прочитать статью целиком. Это бесплатно.
							<?php else: ?>
								Подпишитесь на автора, чтобы получить доступ к эксклюзивным материалам.
							<?php endif; ?>
						</p>
						<div class="paywall-cta__actions">
							<?php if ($viewModel->currentUserId === 0): ?>
								<a href="<?= route('auth.login') ?>?redirect=<?= urlencode('/story/' . $viewModel->story['id']) ?>" 
								   class="btn btn-pill btn-primary">
									Войти
								</a>
								<?php if (!config('invitations.config.invitations_enabled')): ?>
									<a href="<?= route('auth.register') ?>" class="btn btn-pill btn-outline">
										Зарегистрироваться
									</a>
								<?php endif; ?>
							<?php else: ?>
								<form action="/subscribe/user/<?= (int)$viewModel->story['user_id'] ?>" method="POST">
									<?= csrf_field() ?>
									<button type="submit" class="btn btn-pill btn-primary">
										+ Подписаться на <?= e($viewModel->story['author_name']) ?>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>


            <!-- Метаданные (byline) -->
<div class="byline">
     
     <?php partial('Votes::_voters', [
        'type' => 'story',
        'id' => (int)$viewModel->story['id'],
        'score' => (int)$viewModel->story['score'],
        'currentVoteState' => $viewModel->currentStoryVote,
        'canDownvote' => $viewModel->canUserDownvote,
        'isLoggedIn' => $viewModel->currentUserId > 0,
        'contentOwnerId' => $viewModel->isAuthor,
        'inline' => true 
    ]); ?>
                    
    <span class="divider">|</span>
    
	<?php $commentsCount = (int)($viewModel->story['comments_count'] ?? 0); ?>
	<a href="<?= route('story.show', ['id' => $viewModel->story['id']]) ?>#comments" class="action-link action-link--neutral">
		💬 <?= $commentsCount; ?>
	</a>

    <!-- Иконки действий + dropdown меню -->
    <div class="icon-actions">
        <!-- Подписка на комментарии (только автор) -->
        <?php if ($viewModel->isAuthor): ?>
            <form method="POST" 
                  action="/story/<?= $viewModel->story['id'] ?>/follow" 
                  class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" 
                        class="icon-btn <?= $viewModel->story['user_is_following'] ? 'is-active' : '' ?>"
                        title="<?= $viewModel->story['user_is_following'] ? 'Отписаться от комментариев' : 'Подписаться на комментарии' ?>"
                        aria-label="Подписка на комментарии">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </button>
            </form>
        <?php endif; ?>

        <!-- Закладка (для всех залогиненных) -->
        <?php if ($viewModel->currentUserId > 0): ?>
            <form method="POST" 
                  action="/saved/toggle/<?= (int)$viewModel->story['id'] ?>" 
                  class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" 
                        class="icon-btn <?= $viewModel->isStorySaved ? 'is-active' : '' ?>"
                        title="<?= $viewModel->isStorySaved ? 'Убрать из закладок' : 'Сохранить в закладки' ?>"
                        aria-label="Закладка">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                </button>
            </form>
        <?php endif; ?>

        <!-- DROPDOWN МЕНЮ "ТРИ ТОЧКИ" -->
        <div class="story-menu-wrapper" id="story-menu-wrapper">
            <button type="button" 
                    class="icon-btn story-menu-trigger" 
                    id="story-menu-trigger"
                    aria-haspopup="true" 
                    aria-expanded="false"
                    aria-label="Больше действий"
                    title="Больше действий">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="1"/>
                    <circle cx="12" cy="5" r="1"/>
                    <circle cx="12" cy="19" r="1"/>
                </svg>
            </button>

            <!-- Выпадающее меню -->
            <div class="story-menu-dropdown" id="story-menu-dropdown" role="menu">
                
                <!-- Редактировать (автор или админ) -->
                <?php if ($viewModel->currentUserId > 0 && ($viewModel->isAuthor || $viewModel->isAdmin) && !$isStoryDeleted): ?>
                    <a href="<?= route('story.edit', ['id' => $viewModel->story['id']]) ?>" 
                       class="story-menu-item" role="menuitem">
                        <span class="story-menu-item__icon">✏️</span>
                        Редактировать
                    </a>
                <?php endif; ?>

                <?php if ($viewModel->isAuthor && !empty($viewModel->story['has_paywall']) && !$isStoryDeleted): ?>
                    <button type="button" 
                            class="story-menu-item" 
                            role="menuitem"
                            id="btn-show-friend-links"
                            data-story-id="<?= (int)$viewModel->story['id'] ?>">
                        <span class="story-menu-item__icon">🔗</span>
                        Дружеские ссылки
                    </button>
                <?php endif; ?>

                <button type="button" 
                        class="story-menu-item" 
                        role="menuitem"
                        data-copy-link="<?= route('story.show', ['id' => $viewModel->story['id']]) ?>">
                    <span class="story-menu-item__icon">🔗</span>
                    Скопировать ссылку
                </button>

                <?php if ($viewModel->currentUserId > 0 && !$viewModel->isAuthor): ?>
                    <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$viewModel->story['id']]) ?>"
                       class="story-menu-item"
                       role="menuitem"
                       data-confirm="Вы уверены, что хотите подать жалобу?">
                        <span class="story-menu-item__icon">🚩</span>
                        Пожаловаться
                    </a>
                <?php endif; ?>

                <?php if ($viewModel->isAdmin): ?>
                    <div class="story-menu-divider"></div>
                    
                    <!-- Staff Pick -->
                    <?php if (!$isStoryDeleted): ?>
                        <form method="POST" 
                              action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/toggle-pick" 
                              class="story-menu-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="story-menu-item" role="menuitem">
                                <span class="story-menu-item__icon">⭐</span>
                                <?= !empty($viewModel->story['is_staff_pick']) ? 'Убрать из выбора' : 'В выбор редакции' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Удалить/Восстановить -->
                    <?php if ($isStoryDeleted): ?>
                        <form action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/restore" 
                              method="POST" class="story-menu-form">
                            <?= csrf_field() ?>
                            <button type="submit" 
                                    class="story-menu-item story-menu-item--success" 
                                    role="menuitem"
                                    data-confirm="Восстановить статью?">
                                <span class="story-menu-item__icon">♻️</span>
                                Восстановить
                            </button>
                        </form>
                    <?php else: ?>
                        <form action="/admin/stories/<?= (int)$viewModel->story['id'] ?>/delete" 
                              method="POST" class="story-menu-form">
                            <?= csrf_field() ?>
                            <button type="submit" 
                                    class="story-menu-item story-menu-item--danger" 
                                    role="menuitem"
                                    data-confirm="Удалить статью?">
                                <span class="story-menu-item__icon">🗑️</span>
                                Удалить
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($viewModel->isAuthor && !empty($viewModel->story['has_paywall']) && !$isStoryDeleted): ?>
    <dialog class="modal" id="friend-links-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__content">
            <div class="modal__header">
                <h3>🔗 Дружеские ссылки</h3>
                <button type="button" class="icon-btn friend-links-modal__close" id="btn-close-friend-links" aria-label="Закрыть">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <div class="friend-links-modal__body">
                <p class="friend-links-modal__description">
                    Создайте специальную ссылку, чтобы поделиться этой статьей с друзьями бесплатно, 
                    даже если они не подписаны на вас.
                </p>
                
                <button type="button" 
                        class="btn btn-primary btn-sm" 
                        id="btn-create-friend-link"
                        data-story-id="<?= (int)$viewModel->story['id'] ?>">
                    + Создать новую ссылку
                </button>
                
                <div id="friend-links-list" class="friend-links-list">
                    <p class="hint">Загрузка ссылок...</p>
                </div>
            </div>
        </div>
    </dialog>
    <?php endif; ?>

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
			
			  <div class="byline">
			
			            <?php if (!empty($viewModel->story['tags_with_names'])): ?>
                <span class="tags">
                    <?php foreach ($viewModel->story['tags_with_names'] as $tagData): ?>
                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag tag-<?= e($tagData['slug']); ?>">
                            <?= e($tagData['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </span>

          
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
			 </div>
			
        </div>
 
</article>

<div class="comment_form_container" id="comment-form-container">
    <?php if ($viewModel->currentUserId > 0 && !$isStoryDeleted): ?>
        <h3>Оставить комментарий</h3>
        <form action="/comments/create" method="POST" id="main-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="story_id" value="<?= (int)$viewModel->story['id'] ?>">
            <input type="hidden" name="parent_id" id="form-parent-id" value="">
            
            <!-- 🔗 Блок inline-цитаты (скрыт по умолчанию, показывается JS) -->
            <div class="inline-quote-block" id="inline-quote-block">
                <div class="inline-quote-block__header">
                    <span class="inline-quote-block__label">💬 Комментарий к фрагменту:</span>
                    <button type="button" class="inline-quote-block__close" id="btn-clear-quote" aria-label="Убрать цитату">✕</button>
                </div>
                <blockquote class="inline-quote-block__text" id="inline-quote-text"></blockquote>
                
                <!-- Hidden-поля для highlight (заполняются JS) -->
                <input type="hidden" name="highlight_quote" id="form-highlight-quote" value="">
                <input type="hidden" name="highlight_block_index" id="form-highlight-block-index" value="">
                <input type="hidden" name="highlight_block_type" id="form-highlight-block-type" value="">
                <input type="hidden" name="highlight_start_offset" id="form-highlight-start-offset" value="">
                <input type="hidden" name="highlight_end_offset" id="form-highlight-end-offset" value="">
            </div>

            <textarea name="comment_text" id="form-comment-textarea" required
                placeholder="Ваш комментарий... (поддерживается Markdown)"></textarea>

			<div class="mt1">
				<button type="submit">Опубликовать комментарий</button>
				<button type="button" id="btn-cancel-reply">Отмена</button>
			</div>
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


<div class="lightbox-overlay" id="lightbox" role="dialog" aria-modal="true" aria-hidden="true">
	<button type="button" class="lightbox-close" aria-label="Закрыть">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
			<line x1="18" y1="6" x2="6" y2="18"></line>
			<line x1="6" y1="6" x2="18" y2="18"></line>
		</svg>
	</button>
	
	<div class="lightbox-content">
		<img src="" alt="" class="lightbox-image">
		<div class="lightbox-caption"></div>
	</div>
	
	<div class="lightbox-spinner" aria-label="Загрузка">
		<div class="spinner"></div>
	</div>
</div>

<script nonce="<?= csp_nonce(); ?>">

/**
 * Friend Links Manager
 */
(function() {
    'use strict';

    const btnShowFriendLinks = document.getElementById('btn-show-friend-links');
    const friendLinksModal = document.getElementById('friend-links-modal');
    const btnCloseFriendLinks = document.getElementById('btn-close-friend-links');
    const btnCreateFriendLink = document.getElementById('btn-create-friend-link');
    const friendLinksList = document.getElementById('friend-links-list');

    if (!btnShowFriendLinks || !friendLinksModal) return;

    const storyId = btnShowFriendLinks.dataset.storyId;

    // Открыть/закрыть через CSS-класс (без style="")
    function openModal() {
        friendLinksModal.classList.add('is-open');
    }

    function closeModal() {
        friendLinksModal.classList.remove('is-open');
    }

    // Показать модальное окно
    btnShowFriendLinks.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation(); // Не открывать dropdown
        openModal();
        await loadFriendLinks();
    });

    // Закрыть модальное окно
    btnCloseFriendLinks?.addEventListener('click', closeModal);

    // Закрыть по клику на backdrop
    friendLinksModal.querySelector('.friend-links-modal__backdrop')?.addEventListener('click', closeModal);

    // Закрыть по Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && friendLinksModal.classList.contains('is-open')) {
            closeModal();
        }
    });

    // Создать новую ссылку
    btnCreateFriendLink?.addEventListener('click', async () => {
        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.content : '';

            const formData = new FormData();
            if (csrfToken) formData.append('csrf_token', csrfToken);

            const response = await fetch(`/stories/${storyId}/friend-link`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (data.success) {
                await loadFriendLinks();
                showNotification('Ссылка создана!', 'success');
            } else {
                showNotification(data.error || 'Ошибка создания ссылки', 'error');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            showNotification('Ошибка сети', 'error');
        }
    });

    // Загрузить список ссылок
    async function loadFriendLinks() {
        try {
            const response = await fetch(`/stories/${storyId}/friend-links`);
            const data = await response.json();

            if (data.success) {
                renderFriendLinks(data.links);
            } else {
                friendLinksList.innerHTML = '<p class="hint">Ошибка загрузки ссылок</p>';
            }
        } catch (error) {
            console.error('Ошибка:', error);
            friendLinksList.innerHTML = '<p class="hint">Ошибка сети</p>';
        }
    }

    // Отрисовать список ссылок
    function renderFriendLinks(links) {
        if (!links || links.length === 0) {
            friendLinksList.innerHTML = '<p class="hint">Пока нет созданных ссылок</p>';
            return;
        }

        const html = links.map(link => {
            const url = `${window.location.origin}/story/${storyId}?fl=${link.token}`;
            const createdDate = new Date(link.created_at).toLocaleDateString('ru-RU');
            const usesText = link.max_uses 
                ? `${link.uses_count} / ${link.max_uses}` 
                : `${link.uses_count} использований`;

            return `
                <div class="friend-link-item">
                    <div class="friend-link-item__url">
                        <input type="text" value="${url}" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-copy" data-url="${url}">
                            📋 Копировать
                        </button>
                    </div>
                    <div class="friend-link-item__meta">
                        <small>Создана: ${createdDate} | ${usesText}</small>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete" data-link-id="${link.id}">
                            🗑️
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        friendLinksList.innerHTML = html;

        // Привязать обработчики
        friendLinksList.querySelectorAll('.btn-copy').forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    showNotification('Ссылка скопирована!', 'success');
                });
            });
        });

        friendLinksList.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Удалить эту ссылку?')) return;

                const linkId = btn.dataset.linkId;
                try {
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = csrfMeta ? csrfMeta.content : '';

                    const formData = new FormData();
                    if (csrfToken) formData.append('csrf_token', csrfToken);

                    const response = await fetch(`/stories/friend-link/${linkId}/delete`, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const data = await response.json();

                    if (data.success) {
                        await loadFriendLinks();
                        showNotification('Ссылка удалена', 'success');
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка удаления', 'error');
                }
            });
        });
    }

    // Показать уведомление (toast)
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
})();



/**
 * Inline Comments
 */
(function() {
    'use strict';

    const articleBody = document.querySelector('.story_content.article-body');
    if (!articleBody) return;

    const isLoggedIn = <?= $viewModel->currentUserId > 0 ? 'true' : 'false' ?>;
    if (!isLoggedIn) return;

    // Элементы существующей формы
    const form = document.getElementById('main-comment-form');
    const formContainer = document.getElementById('comment-form-container');
    const quoteBlock = document.getElementById('inline-quote-block');
    const quoteText = document.getElementById('inline-quote-text');
    const textarea = document.getElementById('form-comment-textarea');
    const btnClearQuote = document.getElementById('btn-clear-quote');

    // Hidden-поля
    const fields = {
        quote: document.getElementById('form-highlight-quote'),
        blockIndex: document.getElementById('form-highlight-block-index'),
        blockType: document.getElementById('form-highlight-block-type'),
        startOffset: document.getElementById('form-highlight-start-offset'),
        endOffset: document.getElementById('form-highlight-end-offset'),
    };

    if (!form || !formContainer) return;

    let popup = null;

    // ============================================================
    // 1. Popup при выделении
    // ============================================================
    document.addEventListener('mouseup', (e) => {
        if (popup && popup.contains(e.target)) return;
        if (formContainer.contains(e.target)) return;

        setTimeout(() => {
            const selection = window.getSelection();
            const selectedText = selection.toString().trim();

            if (selectedText.length < 3 || !articleBody.contains(selection.anchorNode)) {
                hidePopup();
                return;
            }

            const blockEl = selection.anchorNode.nodeType === 1
                ? selection.anchorNode.closest('[data-block-index]')
                : selection.anchorNode.parentElement?.closest('[data-block-index]');

            if (!blockEl) {
                hidePopup();
                return;
            }

            const rect = selection.getRangeAt(0).getBoundingClientRect();
            showPopup(rect, {
                text: selectedText,
                blockIndex: blockEl.dataset.blockIndex || '',
                blockType: blockEl.dataset.blockType || 'paragraph',
                startOffset: selection.getRangeAt(0).startOffset,
                endOffset: selection.getRangeAt(0).endOffset,
            });
        }, 10);
    });

    function showPopup(rect, data) {
        if (!popup) {
            popup = document.createElement('div');
            popup.className = 'inline-comment-popup';
            popup.innerHTML = `<button type="button">💬 Комментировать</button>`;
            popup.querySelector('button').addEventListener('click', () => scrollToFormWithQuote(data));
            document.body.appendChild(popup);
        }

        popup._data = data;
        popup.style.display = 'block';
        popup.style.top = `${rect.top + window.scrollY - 45}px`;
        popup.style.left = `${rect.left + window.scrollX + rect.width / 2}px`;
    }

    function hidePopup() {
        if (popup) popup.style.display = 'none';
    }

    // ============================================================
    // 2. Скролл к форме с цитатой
    // ============================================================
    function scrollToFormWithQuote(data) {
        hidePopup();

        // Заполняем hidden-поля
        fields.quote.value = data.text;
        fields.blockIndex.value = data.blockIndex;
        fields.blockType.value = data.blockType;
        fields.startOffset.value = data.startOffset;
        fields.endOffset.value = data.endOffset;

        // Показываем блок цитаты
        quoteText.textContent = `«${data.text}»`;
        quoteBlock.style.display = 'block';

        // Сбрасываем parent_id (это не ответ на комментарий)
        document.getElementById('form-parent-id').value = '';

        // Плавно скроллим к форме
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Фокус на textarea после скролла
        setTimeout(() => textarea.focus(), 500);
    }

    // ============================================================
    // 3. Кнопка "Убрать цитату"
    // ============================================================
    btnClearQuote?.addEventListener('click', clearQuote);

    function clearQuote() {
        quoteBlock.style.display = 'none';
        fields.quote.value = '';
        fields.blockIndex.value = '';
        fields.blockType.value = '';
        fields.startOffset.value = '';
        fields.endOffset.value = '';
    }

    // Кнопка "Отмена" тоже должна очищать цитату
    document.getElementById('btn-cancel-reply')?.addEventListener('click', clearQuote);

    // ============================================================
    // 4. Подсветка существующих highlights при загрузке
    // ============================================================
    const storyId = <?= (int)$viewModel->story['id'] ?>;

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const resp = await fetch(`/comments/highlights/${storyId}`);
            const data = await resp.json();
            if (!data.success || !data.highlights) return;

            data.highlights.forEach(highlightInArticle);
        } catch (e) {
            console.error('Не удалось загрузить highlights:', e);
        }
    });

    function highlightInArticle(h) {
        const quote = h.quoted_text;
        const blockIndex = h.block_index;
        if (!quote) return;

        const block = blockIndex !== null
            ? articleBody.querySelector(`[data-block-index="${blockIndex}"]`)
            : articleBody;

        if (!block) return;

        const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
        let node;
        while (node = walker.nextNode()) {
            const idx = node.textContent.indexOf(quote);
            if (idx !== -1) {
                const range = document.createRange();
                range.setStart(node, idx);
                range.setEnd(node, idx + quote.length);

                const mark = document.createElement('mark');
                mark.className = 'inline-comment-highlight';
                mark.dataset.commentId = h.comment_id;
                mark.title = `${h.author_name}: нажмите, чтобы увидеть комментарий`;

                mark.addEventListener('click', () => {
                    const target = document.getElementById(`comment-block-${h.comment_id}`);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        target.classList.add('is-highlighted-temp');
                        setTimeout(() => target.classList.remove('is-highlighted-temp'), 2000);
                    }
                });

                try {
                    range.surroundContents(mark);
                } catch (e) {
                    console.warn('Не удалось подсветить:', e);
                }
                break;
            }
        }
    }
	
	
    // ============================================================
    // 5. Клик по цитате в комментарии → прокрутка к highlight в статье
    // ============================================================
    document.querySelectorAll('.inline-comment-quote[data-highlight-target]').forEach(quoteEl => {
        const handler = () => {
            const commentId = quoteEl.dataset.highlightTarget;
            const mark = articleBody.querySelector(`.inline-comment-highlight[data-comment-id="${commentId}"]`);

            if (mark) {
                mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
                mark.classList.add('is-flash');
                setTimeout(() => mark.classList.remove('is-flash'), 2000);
            } else {
                // Highlight не найден в статье (например, текст изменился)
                // Прокручиваем к началу статьи как fallback
                articleBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        quoteEl.addEventListener('click', handler);
        quoteEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handler();
            }
        });
    });

    // ============================================================
    // 6. Переход по якорю #highlight-X из глобальной ленты комментариев
    // ============================================================
    function handleHighlightHash() {
        const hash = window.location.hash;
        if (!hash.startsWith('#highlight-')) return;

        const commentId = hash.replace('#highlight-', '');
        const mark = articleBody.querySelector(`.inline-comment-highlight[data-comment-id="${commentId}"]`);

        if (mark) {
            // Даём странице полностью отрисоваться
            setTimeout(() => {
                mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
                mark.classList.add('is-flash');
                setTimeout(() => mark.classList.remove('is-flash'), 2500);
            }, 300);
        }
    }

    handleHighlightHash();
    window.addEventListener('hashchange', handleHighlightHash);
	
})();

/**
 * Reading Time Tracker (Medium-style)
 * 
 * Отслеживает реальное время, которое пользователь проводит за чтением статьи.
 * Данные используются в RecommendationService для персонализации ленты.
 * 
 * Принципы:
 * - Считаем ТОЛЬКО активное время (вкладка в фокусе + пользователь скроллит/двигает мышью)
 * - Отправляем на сервер батчами (каждые 30 сек), чтобы не спамить запросами
 * - Не трекаем собственные статьи автора
 */
(function() {
    'use strict';

    const CONFIG = {
        SEND_INTERVAL: 30000,        // Отправляем на сервер каждые 30 секунд
        MIN_SECONDS_TO_SEND: 5,      // Минимум 5 сек чтения для отправки
        IDLE_TIMEOUT: 30000,         // 30 сек без активности = "ушёл"
        API_URL: '/api/stories/track-reading',
    };

    class ReadingTracker {
        constructor(storyId) {
            this.storyId = storyId;
            this.readSeconds = 0;
            this.unsentSeconds = 0;
            this.isActive = true;
            this.lastActivityTime = Date.now();
            this.intervalId = null;
            this.activityTimeoutId = null;
        }

        /**
         * Запуск трекинга
         */
        start() {
            if (!this.storyId || this.storyId <= 0) {
                console.log('[ReadingTracker] No story ID, skipping');
                return;
            }

            console.log(`[ReadingTracker] Tracking story #${this.storyId}`);

            // Отслеживаем активность пользователя
            this._setupActivityListeners();

            // Отслеживаем видимость вкладки
            this._setupVisibilityListener();

            // Периодическая отправка на сервер
            this.intervalId = setInterval(() => {
                this._tick();
            }, 1000);

            // Отправка при уходе со страницы
            window.addEventListener('beforeunload', () => this._flush());
            window.addEventListener('pagehide', () => this._flush());
        }

        /**
         * Остановка трекинга
         */
        stop() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
            if (this.activityTimeoutId) {
                clearTimeout(this.activityTimeoutId);
                this.activityTimeoutId = null;
            }
            this._flush();
        }

        /**
         * Ежесекундный тик
         */
        _tick() {
            const now = Date.now();
            const timeSinceActivity = now - this.lastActivityTime;

            // Считаем время только если:
            // 1. Вкладка активна
            // 2. Была активность за последние IDLE_TIMEOUT мс
            if (this.isActive && timeSinceActivity < CONFIG.IDLE_TIMEOUT) {
                this.readSeconds++;
                this.unsentSeconds++;
            }

            // Отправляем батч каждые SEND_INTERVAL мс
            if (this.unsentSeconds >= CONFIG.SEND_INTERVAL / 1000) {
                this._sendToServer(this.unsentSeconds);
                this.unsentSeconds = 0;
            }
        }

        /**
         * Отправка данных на сервер
         */
        _sendToServer(seconds) {
            if (seconds < CONFIG.MIN_SECONDS_TO_SEND) return;

            // Используем sendBeacon для надёжности (работает даже при закрытии вкладки)
            const formData = new FormData();
            formData.append('story_id', this.storyId);
            formData.append('seconds', seconds);

            // Получаем CSRF токен из meta-тега
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.content : '';
            
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            // sendBeacon — асинхронно, не блокирует закрытие страницы
            if (navigator.sendBeacon) {
                navigator.sendBeacon(CONFIG.API_URL, formData);
            } else {
                // Fallback для старых браузеров
                fetch(CONFIG.API_URL, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    keepalive: true,
                }).catch(err => console.error('[ReadingTracker]', err));
            }
        }

        /**
         * Финальная отправка накопленных данных
         */
        _flush() {
            if (this.unsentSeconds >= CONFIG.MIN_SECONDS_TO_SEND) {
                this._sendToServer(this.unsentSeconds);
                this.unsentSeconds = 0;
            }
        }

        /**
         * Слушатели активности пользователя
         */
        _setupActivityListeners() {
            const resetActivity = () => {
                this.lastActivityTime = Date.now();
            };

            ['mousemove', 'scroll', 'keydown', 'click', 'touchstart'].forEach(event => {
                document.addEventListener(event, resetActivity, { passive: true });
            });
        }

        /**
         * Слушатель видимости вкладки
         */
        _setupVisibilityListener() {
            document.addEventListener('visibilitychange', () => {
                this.isActive = !document.hidden;
                if (this.isActive) {
                    this.lastActivityTime = Date.now();
                }
            });

            window.addEventListener('blur', () => { this.isActive = false; });
            window.addEventListener('focus', () => {
                this.isActive = true;
                this.lastActivityTime = Date.now();
            });
        }
    }

    // =========================================================================
    // АВТОЗАПУСК на странице статьи
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Ищем article с data-story-id
        const article = document.querySelector('article[data-story-id]');
        if (!article) return;

        const storyId = parseInt(article.dataset.storyId, 10);
        const isAuthor = article.dataset.isAuthor === '1';

        // Не трекаем свои статьи
        if (isAuthor) {
            console.log('[ReadingTracker] Skipping own story');
            return;
        }

        // Запускаем трекер
        const tracker = new ReadingTracker(storyId);
        tracker.start();

        // Сохраняем в window для отладки
        window.__readingTracker = tracker;
    });
})();

/**
 * Lightbox — полноэкранный просмотр изображений
 */
(function () {
    'use strict';

    const overlay = document.getElementById('lightbox');
    if (!overlay) return;

    const image = overlay.querySelector('.lightbox-image');
    const caption = overlay.querySelector('.lightbox-caption');
    const spinner = overlay.querySelector('.lightbox-spinner');
    const closeBtn = overlay.querySelector('.lightbox-close');

    let lastFocused = null;

    // Открытие лайтбокса
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.lightbox-trigger');
        if (!trigger) return;

        const fullSrc = trigger.getAttribute('data-full-src');
        const alt = trigger.querySelector('img')?.getAttribute('alt') || '';
        const captionText = trigger.getAttribute('data-caption') || '';

        if (!fullSrc) return;

        lastFocused = document.activeElement;
        openLightbox(fullSrc, alt, captionText);
    });

    // Закрытие по клику на фон (вне картинки)
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.classList.contains('lightbox-content')) {
            closeLightbox();
        }
    });

    // Закрытие по кнопке ✕
    closeBtn.addEventListener('click', closeLightbox);

    // Закрытие по Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.getAttribute('aria-hidden') === 'false') {
            closeLightbox();
        }
    });

    function openLightbox(src, alt, captionText) {
        // Показываем спиннер, скрываем картинку
        spinner.style.display = 'flex';
        image.style.opacity = '0';
        caption.textContent = '';

        // Предзагрузка
        const loader = new Image();
        loader.onload = function () {
            image.src = src;
            image.alt = alt;
            spinner.style.display = 'none';
            image.style.opacity = '1';
            if (captionText) {
                caption.textContent = captionText;
            }
        };
        loader.onerror = function () {
            spinner.style.display = 'none';
            image.src = src;
            image.alt = alt;
            image.style.opacity = '1';
        };
        loader.src = src;

        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden'; // Блокируем скролл страницы
        overlay.classList.add('is-open');

        // Фокус на кнопку закрытия (a11y)
        setTimeout(() => closeBtn.focus(), 100);
    }

    function closeLightbox() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        setTimeout(() => {
            image.src = '';
            image.style.opacity = '0';
            caption.textContent = '';
        }, 300);

        // Возвращаем фокус
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }
})();
</script>