<?php
$currentUserId = \W3a\Core\Auth\Auth::check() ? \W3a\Core\Auth\Auth::id() : 0;
$isOwnProfile = ($currentUserId === (int)$profileUser['id']);
$profileUrl = route('user.profile', ['username' => $profileUser['username']]);
$activeTab = $activeTab ?? 'stories';
?>

<div class="tt-layout">

    <main class="tt-feed">

        <section class="profile-hero">
            <div class="profile-hero__top">
                <?php if (!empty($profileUser['avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($profileUser['avatar'], 0, 2) ?>/<?= e($profileUser['avatar']) ?>"
                         class="profile-avatar-large profile-hero__avatar"
                         alt="<?= e(mb_substr($profileUser['username'], 0, 1)) ?>">
                <?php else: ?>
                    <div class="profile-avatar-placeholder-large profile-hero__avatar">
                        <?= e(mb_substr($profileUser['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <h1 class="profile-username"><?= e($profileUser['username']) ?></h1>

                <?php if ($followersCount > 0): ?>
                    <p class="profile-hero__followers"><?= (int)$followersCount ?> подписчик<?= plural((int)$followersCount, ['', 'а', 'ов']) ?></p>
                <?php endif; ?>

                <?php if (!empty($profileUser['bio'])): ?>
                    <p class="profile-bio"><?= nl2br(e($profileUser['bio'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($isOwnProfile): ?>
                <div class="profile-actions">
                    <a href="<?= route('user.stats') ?>" class="btn btn-sm">📊 Статистика</a>
                    <a href="<?= route('user.history') ?>" class="btn btn-sm">📖 История чтения</a>
                    <a href="<?= route('account.settings') ?>" class="btn btn-sm">⚙️ Настройки</a>
                </div>
            <?php elseif ($currentUserId > 0): ?>
                <div class="profile-actions">
                    <form action="<?= route('messages.start', ['userId' => $profileUser['id']]) ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm">✉️ Написать</button>
                    </form>

                    <form action="/subscribe/user/<?= (int)$profileUser['id'] ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm <?= $isFollowing ? 'btn-secondary' : 'btn-primary' ?>">
                            <?= $isFollowing ? '✓ Подписаны' : '➕ Подписаться' ?>
                        </button>
                    </form>

                    <form action="/mute/toggle/<?= (int)$profileUser['id'] ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline" title="<?= $isMuted ? 'Читать' : 'Игнорировать' ?>">
                            <?= $isMuted ? '🔊' : '🔇' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (\W3a\Core\Auth\Auth::isModerator() && $profileUser['id'] !== \W3a\Core\Auth\Auth::id()): ?>
                <div class="mod-actions mt-4">
                    <a href="/mod/notes?user_id=<?= $profileUser['id'] ?>" class="btn btn-sm btn-outline">📝 Заметка</a>
                    <?php if (empty($profileUser['is_banned'])): ?>
                        <form method="POST" action="<?= route('mod.ban', ['id' => $profileUser['id']]) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="ban">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Забанить <?= e($profileUser['username']) ?>?">🚫 Бан</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= route('mod.ban', ['id' => $profileUser['id']]) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unban">
                            <button type="submit" class="btn btn-sm btn-success" data-confirm="Разбанить <?= e($profileUser['username']) ?>?">✅ Разбан</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <nav class="nav br-none profile-tabs" aria-label="Профиль">
            <a href="<?= $profileUrl ?>" class="<?= $activeTab === 'stories' ? 'is-active' : '' ?>">Публикации</a>
            <a href="<?= route('user.profile.collections', ['username' => $profileUser['username']]) ?>" class="<?= $activeTab === 'collections' ? 'is-active' : '' ?>">
                Коллекции <?php if ($collectionsCount > 0): ?><span class="profile-tabs__count"><?= (int)$collectionsCount ?></span><?php endif; ?>
            </a>
        </nav>

<?php if ($activeTab === 'stories'): ?>
        <section class="user-stories">
            <?php if (empty($stories)): ?>
                <p class="hint">Пользователь пока не опубликовал ни одной статьи.</p>
            <?php else: ?>
                <div class="tt-feed__list">
                    <?php foreach ($stories as $story): ?>
                        <?php partial('Stories::_story_row', [
                            'story' => $story,
                            'currentUserId' => $currentUserId,
                            'isAdmin' => \W3a\Core\Auth\Auth::isModerator(),
                            'currentVotes' => [],
                            'newCommentsMap' => [],
                            'hideAuthor' => true,
                        ]); ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-6">
                        <?= pagination($currentPage, $totalPages) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
<?php else: ?>
        <section class="user-collections">
            <?php if (!empty($isOwner)): ?>
            <div class="section-header">
                <h2 class="section-title">Коллекции</h2>
                <a href="<?= route('collections.create') ?>" class="btn btn-pill btn-primary">+ Новая коллекция</a>
            </div>
            <?php endif; ?>

            <?php if (empty($collectionsAll)): ?>
                <?php if (empty($isOwner)): ?>
                <div class="empty-state">
                    <h2>Пока нет коллекций</h2>
                    <p>Коллекции позволяют объединять статьи в серии с общим сюжетом.</p>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h2>Пока нет коллекций</h2>
                    <p>Коллекции позволяют объединять статьи в серии с общим сюжетом.</p>
                    <div class="empty-state__actions">
                        <a href="<?= route('collections.create') ?>" class="btn btn-pill btn-primary">
                            Создать первую коллекцию
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="collections-grid">
                    <?php foreach ($collectionsAll as $collection): ?>
                        <?php partial('Collections::_card', [
                            'collection' => $collection,
                            'profileUser' => $profileUser,
                            'isOwner' => !empty($isOwner),
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
<?php endif; ?>

    </main>

    <aside class="tt-sidebar">

        <div class="tt-sidebar__block">
            <h3 class="tt-sidebar__title">Статистика</h3>
            <div class="tt-author__info profile-sidebar-stats">
                <p class="profile-sidebar-stats__row"><strong><?= (int)$storiesCount ?></strong> публикаций</p>
                <p class="profile-sidebar-stats__row"><strong><?= (int)$commentsCount ?></strong> комментариев</p>
                <?php if ($collectionsCount > 0): ?>
                    <p class="profile-sidebar-stats__row"><strong><?= (int)$collectionsCount ?></strong> коллекций</p>
                <?php endif; ?>
                <?php
                $karmaClass = $userKarma > 0 ? 'text-success' : ($userKarma < 0 ? 'text-danger' : 'text-muted');
                ?>
                <p class="profile-sidebar-stats__row <?= $karmaClass ?>"><strong><?= $userKarma > 0 ? '+' : '' ?><?= (int)$userKarma ?></strong> кармы</p>
            </div>
        </div>

    </aside>

</div>