<?php
/**
 * Главная страница — лента статей (Teletype-стиль)
 *
 * @var \App\Modules\Stories\ViewModels\HomeFeedViewModel|null $viewModel
 * @var string $title
 */

$useViewModel = isset($viewModel)
    && $viewModel instanceof \App\Modules\Stories\ViewModels\HomeFeedViewModel;

if ($useViewModel):
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
    $allTags = $viewModel->allTags;
    $topAuthors = $viewModel->topAuthors;
?>

<div class="tt-layout">

    <main class="tt-feed">

        <?php if ($viewModel->shouldShowForYou()): ?>
        <section class="tt-section">
            <h2 class="tt-section__title">Рекомендации</h2>
            <div class="tt-feed__list">
                <?php foreach (array_slice($forYou, 0, 5) as $story): ?>
                    <?php partial('Stories::_story_row', [
                        'story' => $story, 'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin, 'currentVotes' => $currentVotes,
                        'newCommentsMap' => $newCommentsMap,
                    ]); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="tt-section">
            <h2 class="tt-section__title">Новые публикации</h2>

            <?php if (!empty($stories)): ?>
                <div class="tt-feed__list">
                    <?php foreach ($stories as $story): ?>
                        <?php partial('Stories::_story_row', [
                            'story' => $story, 'currentUserId' => $currentUserId,
                            'isAdmin' => $isAdmin, 'currentVotes' => $currentVotes,
                            'newCommentsMap' => $newCommentsMap,
                        ]); ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?= pagination($currentPage, $totalPages) ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="tt-empty">
                    <p>Пока нет новых статей.</p>
                    <?php if ($currentUserId > 0): ?>
                        <a href="<?= route('story.form') ?>" class="btn btn-pill btn-primary">Написать</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <aside class="tt-sidebar">

        <?php if (!empty($topAuthors)): ?>
        <div class="tt-sidebar__block">
            <h3 class="tt-sidebar__title">Авторы</h3>
            <div class="tt-authors">
                <?php foreach ($topAuthors as $author): ?>
                    <a href="<?= route('user.profile', ['username' => $author['author_name']]) ?>" class="tt-author">
                        <?php if (!empty($author['avatar'])): ?>
                            <img class="tt-author__avatar" src="/uploads/avatars/<?= substr($author['avatar'], 0, 2) ?>/<?= e($author['avatar']) ?>" alt="">
                        <?php else: ?>
                            <span class="tt-author__avatar tt-author__avatar--placeholder"><?= e(mb_substr($author['author_name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <div class="tt-author__info">
                            <span class="tt-author__name"><?= e($author['author_name']) ?></span>
                            <span class="tt-author__meta"><?= (int)$author['follower_count'] ?> подписчик<?= plural((int)$author['follower_count'], ['', 'а', 'ов']) ?></span>
                        </div>
                        <?php if ($viewModel->isLoggedIn): ?>
                        <form action="/subscribe/user/<?= (int)$author['id'] ?>" method="POST" class="tt-author__form">
                            <?= csrf_field() ?>
                            <button type="submit" class="tt-author__btn <?= !empty($author['is_following']) ? 'tt-author__btn--active' : '' ?>">
                                <?php if (!empty($author['is_following'])): ?>✓<?php else: ?><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><?php endif; ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="tt-author__form"><button type="button" class="tt-author__btn" data-login-modal><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($viewModel->shouldShowTrending()): ?>
        <div class="tt-sidebar__block">
            <h3 class="tt-sidebar__title">В тренде</h3>
            <ol class="tt-trending">
                <?php foreach (array_slice($trending, 0, 5) as $i => $story): ?>
                    <li class="tt-trending__item">
                        <span class="tt-trending__num"><?= $i + 1 ?></span>
                        <div>
                            <a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="tt-trending__link">
                                <?= e($story['title']) ?>
                            </a>
                            <span class="tt-trending__author"><?= e($story['author_name'] ?? '') ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <?php if ($viewModel->shouldShowStaffPicks()): ?>
        <div class="tt-sidebar__block">
            <h3 class="tt-sidebar__title">Выбор редакции</h3>
            <div class="tt-picks">
                <?php foreach (array_slice($staffPicks, 0, 3) as $story): ?>
                    <?php $img = get_story_first_image($story, 'small'); ?>
                    <a href="<?= route('story.show', ['id' => $story['id']]) ?>" class="tt-pick">
                        <?php if ($img): ?>
                            <img class="tt-pick__img" src="<?= e($img) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <div class="tt-pick__text">
                            <span class="tt-pick__title"><?= e($story['title']) ?></span>
                            <span class="tt-pick__author"><?= e($story['author_name'] ?? '') ?><?php $rt = (int)($story['reading_time'] ?? 0); if ($rt > 0): ?> · <?= $rt ?> мин<?php endif; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        </aside>

</div>

<?php else: /* старый формат (фильтры по тегу/автору) */ ?>
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

$isSubscribedEmpty = (($title ?? '') === 'Мои подписки' && !empty($isEmptyState));
?>

<?php if (!empty($tagInfo['slug'])): ?>
    <div class="filter-header">
        <center>
            <h1 class="title"><?= e($tagInfo['name']) ?></h1>
            <div class="filter-header__hint"><?= e($tagInfo['description']); ?></div>
            <?php if (!empty($primaryWikiPage['title'])): ?>
                <p class="filter-header__hint">
                    Wiki: <a href="/t/<?= e($tagInfo['slug']) ?>/wiki/<?= e($primaryWikiPage['slug']) ?>"><?= e($primaryWikiPage['title']) ?></a>
                </p>
            <?php endif; ?>
        </center>
    </div>
<?php endif; ?>

<?php if (!empty($author)): ?>
    <div class="filter-header mt-4">
        <h1>Публикации: <?= e($author) ?></h1>
        <a href="/" class="filter-header__reset">× Сбросить фильтр</a>
    </div>
<?php endif; ?>

<?php if (!empty($domain)): ?>
    <div class="filter-header">
        <h1>Публикации по домену: <?= e($domain) ?></h1>
        <a href="/" class="filter-header__reset">× Сбросить фильтр</a>
    </div>
<?php endif; ?>

<?php if ($isSubscribedEmpty): ?>
    <div class="empty-state empty-state--subscribed">
        <h2>📭 У вас пока нет подписок</h2>
        <p class="hint">Здесь будут появляться новые истории от авторов и по темам, на которые вы подпишетесь.</p>
        <div class="empty-state__actions">
            <a href="/tags" class="btn btn-pill btn-outline">🏷️ Посмотреть теги</a>
            <a href="/" class="btn btn-pill btn-primary">🏠 На главную</a>
        </div>
    </div>
<?php elseif (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php partial('Stories::_story_row', [
                'story' => $story, 'currentUserId' => $currentUserId,
                'isAdmin' => $isAdmin, 'currentVotes' => $currentVotes,
                'newCommentsMap' => $newCommentsMap, 'hideAuthor' => false,
            ]); ?>
        <?php endforeach; ?>
    </ol>
    <?php if ($totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state"><p>Лента пуста.</p></div>
<?php endif; ?>

<?php endif; ?>