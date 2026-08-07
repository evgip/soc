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
				// Определяем, есть ли у читателя доступ к закрытой части
				$paywallType = $viewModel->story['paywall_type'] ?? 'none';
				$hasAccess = match($paywallType) {
					'none' => true,
					'members' => $viewModel->currentUserId > 0,
					'subscribers' => $viewModel->currentUserId > 0 && ($isFollowing || $viewModel->isAuthor),
					default => true,
				};
				
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
								   class="btn btn-pill btn-accent">
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
									<button type="submit" class="btn btn-pill btn-accent">
										➕ Подписаться на <?= e($viewModel->story['author_name']) ?>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>


            <!-- Метаданные (byline) -->
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
		💬 <?= $commentsCount === 0 ? 'обсудить' : $commentsCount . ' ' . plural($commentsCount, ['комментарий', 'комментария', 'комментариев']) ?>
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

                <!-- Скопировать ссылку (для всех) -->
                <button type="button" 
                        class="story-menu-item" 
                        role="menuitem"
                        data-copy-link="<?= route('story.show', ['id' => $viewModel->story['id']]) ?>">
                    <span class="story-menu-item__icon">🔗</span>
                    Скопировать ссылку
                </button>

                <!-- Пожаловаться (для залогиненных, не автор) -->
                <?php if ($viewModel->currentUserId > 0 && !$viewModel->isAuthor): ?>
                    <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$viewModel->story['id']]) ?>"
                       class="story-menu-item"
                       role="menuitem"
                       data-confirm="Вы уверены, что хотите подать жалобу?">
                        <span class="story-menu-item__icon">🚩</span>
                        Пожаловаться
                    </a>
                <?php endif; ?>

                <!-- Админские действия -->
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

    <!-- Отметить прочитанным (если есть новые комментарии) -->
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

<script nonce="<?= csp_nonce(); ?>">

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
</script>