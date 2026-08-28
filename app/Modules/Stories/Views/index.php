<?php
/**
 * Главная страница — лента статей (Medium-стиль)
 * 
 * @var \App\Modules\Stories\ViewModels\HomeFeedViewModel|null $viewModel
 * @var string $title
 */

// Проверяем, используется ли новый ViewModel или старая логика (для фильтров по тегу/подписок)
$useViewModel = isset($viewModel) 
    && $viewModel instanceof \App\Modules\Stories\ViewModels\HomeFeedViewModel;

if ($useViewModel):
    // ============================================================
    // НОВЫЙ ФОРМАТ: Medium-стиль с секциями и сайдбаром
    // ============================================================
    
    $currentUserId = $viewModel->currentUserId;
    $isAdmin = $viewModel->isAdmin;
    $currentVotes = $viewModel->currentVotes;
    $newCommentsMap = $viewModel->newCommentsMap;
    $stories = $viewModel->stories;
    $forYou = $viewModel->forYou;
    $trending = $viewModel->trending;
    $staffPicks = $viewModel->staffPicks;
    $currentPage = $viewModel->currentPage;
    $totalPages = $viewModel->totalPages;
?>

<div class="home-grid">
    <!-- ========================================================
         ЛЕВАЯ КОЛОНКА: основная лента + рекомендации
         ======================================================== -->
    <div class="home-grid__main">
        
        <!-- СЕКЦИЯ: РЕКОМЕНДАЦИИ ДЛЯ ВАС -->
        <?php if ($viewModel->shouldShowForYou()): ?>
        <section class="feed-section feed-section--for-you">
            <header class="section-header">
                <h2 class="section-title">Рекомендации для вас</h2>
                <p class="section-subtitle">На основе вашей истории чтения и подписок</p>
            </header>
            
            <div class="story-list">
                <?php foreach (array_slice($forYou, 0, 6) as $story): ?>
                    <?php partial('Stories::_story_item', [
                        'story' => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin,
                        'currentVotes' => $currentVotes,
                        'newCommentsMap' => $newCommentsMap,
                        'hideAuthor' => false,
                    ]); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- СЕКЦИЯ: НОВЫЕ ПУБЛИКАЦИИ (основная лента) -->
        <section class="feed-section feed-section--main">
            <header class="section-header">
                <h2 class="section-title">Новые публикации</h2>
            </header>
            
            <?php if (!empty($stories)): ?>
                <ol class="stories">
                    <?php foreach ($stories as $story): ?>
                        <?php partial('Stories::_story_item', [
                            'story' => $story,
                            'currentUserId' => $currentUserId,
                            'isAdmin' => $isAdmin,
                            'currentVotes' => $currentVotes,
                            'newCommentsMap' => $newCommentsMap,
                            'hideAuthor' => false,
                        ]); ?>
                    <?php endforeach; ?>
                </ol>

                <?php if ($totalPages > 1): ?>
                    <?= pagination($currentPage, $totalPages) ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p class="hint">Пока нет новых статей.</p>
                    <?php if ($currentUserId > 0): ?>
                        <a href="<?= route('story.form') ?>" class="btn btn-pill btn-primary">
                            Написать первую статью
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ========================================================
         ПРАВАЯ КОЛОНКА: сайдбар
         ======================================================== -->
    <aside class="home-grid__sidebar">
        
		<!-- СЕКЦИЯ: СЕЙЧАС В ТРЕНДЕ -->
		<?php if ($viewModel->shouldShowTrending()): ?>
		<section class="sidebar-section sidebar-section--trending">
			<header class="sidebar-header">
				<h3 class="section-title">Сейчас в тренде</h3>
			</header>
			
			<ol class="trending-list">
				<?php foreach (array_slice($trending, 0, 5) as $index => $story): ?>
					<li class="trending-item">
						<span class="trending-number"><?= $index + 1 ?></span>
						<div class="trending-content">
							<a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="trending-title">
								<?= e($story['title']) ?>
							</a>
							<div class="trending-meta">
								<a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" class="trending-author">
									<?= e($story['author_name']) ?>
								</a>
								<span class="trending-divider">·</span>
								<span class="trending-date">
									<?= format_date_ru($story['created_at']) ?>
								</span>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php endif; ?>

		<!-- СЕКЦИЯ: ВЫБОР РЕДАКЦИИ -->
		<?php if ($viewModel->shouldShowStaffPicks()): ?>
		<section class="sidebar-section sidebar-section--staff-picks">
			<header class="sidebar-header">
				<h3 class="section-title">Выбор редакции</h3>
			</header>
			
			<div class="staff-picks-list">
				<?php foreach (array_slice($staffPicks, 0, 3) as $story): ?>
					<article class="staff-pick-compact">
						<?php $firstImage = get_story_first_image($story); ?>
						<?php if ($firstImage): ?>
							<a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="staff-pick-compact__image">
								<img src="<?= e($firstImage) ?>" alt="" loading="lazy">
							</a>
						<?php endif; ?>
						
						<h4 class="staff-pick-compact__title">
							<a href="<?= route('story.show', ['id' => $story['id']]) ?>">
								<?= e($story['title']) ?>
							</a>
						</h4>
						<div class="staff-pick-compact__meta">
							<a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>">
								<?= e($story['author_name']) ?>
							</a>
							<span class="staff-pick-compact__divider">·</span>
							<span class="staff-pick-compact__date">
								<?= format_date_ru($story['created_at']) ?>
							</span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

        <!-- СЕКЦИЯ: ПОПУЛЯРНЫЕ ТЕГИ -->
        <?php if (!empty($allTags) && is_array($allTags)): ?>
        <section class="sidebar-section sidebar-section--tags">
            <header class="sidebar-header">
                <h3 class="section-title">Популярные темы</h3>
            </header>
            <div class="sidebar-tags">
                <?php foreach (array_slice($allTags, 0, 10) as $tag): ?>
                    <a href="<?= route('tags.filter', ['tagslug' => e($tag['slug'])]) ?>" class="tag tag-<?= e($tag['slug']) ?>">
                        <?= e($tag['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- СЕКЦИЯ: ПРИСОЕДИНЯЙТЕСЬ (для гостей) -->
        <?php if (!$viewModel->isLoggedIn): ?>
        <section class="sidebar-section sidebar-section--subscribe">
            <h3 class="section-title">Присоединяйтесь</h3>
            <p class="sidebar-text">
                Читайте лучшие публикации, подписывайтесь на авторов и участвуйте в обсуждениях.
            </p>
            <div class="sidebar-actions">
                <a href="<?= route('auth.login') ?>" class="btn btn-pill btn-outline">Войти</a>
                <?php if (config('invitations.config.invitations_enabled')): ?>
                    <a href="<?= route('home') ?>invite/request" class="btn btn-pill btn-primary">Запросить приглашение</a>
                <?php else: ?>
                    <a href="<?= route('auth.register') ?>" class="btn btn-pill btn-primary">Зарегистрироваться</a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>

<?php else: ?>
<!-- ============================================================
     СТАРЫЙ ФОРМАТ: Фильтры по тегу / автору / подписки
     (обратная совместимость)
     ============================================================ -->
<?php
$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$stories = $stories ?? [];
$tagInfo = $tagInfo ?? [];
$author = $author ?? '';
$domain = $domain ?? '';
$sort = $sort ?? 'new';
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 0;
$newCommentsMap = $newCommentsMap ?? [];
$currentVotes = $currentVotes ?? [];

// Специальная страница "Мои подписки" с пустым состоянием
$isSubscribedEmpty = (($title ?? '') === 'Мои подписки' && !empty($isEmptyState));
?>

<!-- Заголовок страницы для фильтров -->
<?php if (!empty($tagInfo['slug'])): ?>
    <div class="filter-header">
	    <center>
			<h1 class="title">
				<?= e($tagInfo['name']) ?>
			</h1>
			<div class="filter-header__hint"><?= e($tagInfo['description']); ?></div>
			
			<?php if (!empty($primaryWikiPage['title'])): ?>
				<p class="filter-header__hint">
					Wiki статья, привязанная к тегу: 
					<a href="/t/<?= e($tagInfo['slug']) ?>/wiki/<?= e($primaryWikiPage['slug']) ?>">
						<?= e($primaryWikiPage['title']) ?>
					</a>
				</p>
			<?php endif; ?>
		</center>
    </div>
<?php endif; ?>

<?php if (!empty($author)): ?>
    <div class="filter-header mt-4">
        <h1 class="filter-header__title">Публикации пользователя: <?= e($author) ?></h1>
        <a href="/" class="filter-header__reset">× Сбросить фильтр</a>
    </div>
<?php endif; ?>

<?php if (!empty($domain)): ?>
    <div class="filter-header">
        <h1 class="filter-header__title">Публикации по домену: <?= e($domain) ?></h1>
        <a href="/" class="filter-header__reset">× Сбросить фильтр</a>
    </div>
<?php endif; ?>

<!-- ПУСТОЕ СОСТОЯНИЕ ДЛЯ ПОДПИСОК -->
<?php if ($isSubscribedEmpty): ?>
    <div class="empty-state empty-state--subscribed">
        <h2>📭 У вас пока нет подписок</h2>
        <p class="hint">
            Здесь будут появляться новые истории от авторов и по темам, на которые вы подпишетесь.<br>
            Это лучший способ собрать персональную ленту без информационного шума.
        </p>
        <div class="empty-state__actions">
            <a href="/tags" class="btn btn-pill btn-outline">🏷️ Посмотреть популярные теги</a>
            <a href="/" class="btn btn-pill btn-primary">🏠 Вернуться на главную</a>
        </div>
    </div>

<!-- ЛЕНТА СТАТЕЙ -->
<?php elseif (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php partial('Stories::_story_item', [
                'story' => $story,
                'currentUserId' => $currentUserId,
                'isAdmin' => $isAdmin,
                'currentVotes' => $currentVotes,
                'newCommentsMap' => $newCommentsMap,
                'hideAuthor' => false,
            ]); ?>
        <?php endforeach; ?>
    </ol>

    <?php if ($totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>

<?php else: ?>
    <div class="empty-state">
        <p class="hint">Лента историй пока пуста.</p>
    </div>
<?php endif; ?>

<?php endif; ?>