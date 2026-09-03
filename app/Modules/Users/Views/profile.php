<?php
$currentUserId = \W3a\Core\Auth\Auth::check() ? \W3a\Core\Auth\Auth::id() : 0;
$isOwnProfile = ($currentUserId === (int)$profileUser['id']);
?>

<div class="profile-header">
    <?php if (!empty($profileUser['avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($profileUser['avatar'], 0, 2) ?>/<?= e($profileUser['avatar']) ?>" 
             class="profile-avatar-large" 
             alt="<?= e(mb_substr($profileUser['username'], 0, 1)) ?>">
    <?php else: ?>
        <div class="profile-avatar-placeholder-large">
            <?= e(mb_substr($profileUser['username'], 0, 1)) ?>
        </div>
    <?php endif; ?>

    <div class="profile-info">
        <h1 class="profile-username"><?= e($profileUser['username']) ?></h1>
        
        <?php if (!empty($profileUser['bio'])): ?>
            <p class="profile-bio"><?= nl2br(e($profileUser['bio'])) ?></p>
        <?php endif; ?>

        <div class="profile-stats">
            <?php if ($collectionsCount > 0): ?>
                <span><strong><?= (int)$collectionsCount ?></strong> <?= plural($collectionsCount, ['коллекция', 'коллекции', 'коллекций']) ?></span>
                <span class="divider">·</span>
            <?php endif; ?>
            
            <span><strong><?= (int)$storiesCount ?></strong> <?= plural($storiesCount, ['публикация', 'публикации', 'публикаций']) ?></span>
            <span class="divider">·</span>
            <span><strong><?= (int)$commentsCount ?></strong> <?= plural($commentsCount, ['комментарий', 'комментария', 'комментариев']) ?></span>
            <span class="divider">·</span>
            <?php
            $karmaClass = $userKarma > 0 ? 'text-success' : ($userKarma < 0 ? 'text-danger' : 'text-muted');
            ?>
            <span class="<?= $karmaClass ?>"><strong><?= $userKarma > 0 ? '+' : '' ?><?= (int)$userKarma ?></strong> кармы</span>
</div>

        <?php if ($isOwnProfile): ?>
            <div class="profile-actions flex gap mt-4">
                <a href="<?= route('user.stats') ?>" class="btn btn-sm">📊 Статистика</a>
                <a href="<?= route('user.history') ?>" class="btn btn-sm">📖 История чтения</a>
            </div>
        <?php endif; ?>

        <?php if (!$isOwnProfile && $currentUserId > 0): ?>
            <div class="profile-actions flex gap mt-4">
                <form action="<?= route('messages.start', ['userId' => $profileUser['id']]) ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">✉️ Написать</button>
                </form>

                <form action="/subscribe/user/<?= (int)$profileUser['id'] ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm <?= $isFollowing ? 'btn-secondary' : 'btn-primary' ?>">
                        <?= $isFollowing ? '✓ Подписаны' : '➕ Подписаться' ?>
                    </button>
                </form>

                <form action="/mute/toggle/<?= (int)$profileUser['id'] ?>" method="POST">
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
    </div>
</div>

<section class="user-stories">
    <h2 class="section-title">Публикации</h2>

    <?php if (empty($stories)): ?>
        <p class="hint">Пользователь пока не опубликовал ни одной статьи.</p>
    <?php else: ?>
        <div class="stories-list">
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

<?php if (!empty($collections)): ?>
<section class="user-collections">
    <div class="section-header">
        <h2 class="section-title">Коллекции</h2>
        <?php if ($collectionsCount > 3): ?>
            <a href="/collections/<?= e($profileUser['username']) ?>" class="section-header__link">
                Все <?= $collectionsCount ?> →
            </a>
        <?php endif; ?>
    </div>

    <div class="collections-preview-grid">
        <?php foreach ($collections as $collection): ?>
            <?php $collectionUrl = '/collections/' . e($profileUser['username']) . '/' . e($collection['slug']); ?>
            <a href="<?= $collectionUrl ?>" class="collection-preview-card">
                <?php if (!empty($collection['cover_url'])): ?>
                    <div class="collection-preview-card__cover">
                        <img src="<?= e($collection['cover_url']) ?>" 
                             alt="<?= e($collection['title']) ?>" 
                             loading="lazy">
                    </div>
                <?php else: ?>
                    <div class="collection-preview-card__cover collection-preview-card__cover--placeholder">
                        <span>📚</span>
                    </div>
                <?php endif; ?>

                <div class="collection-preview-card__body">
                    <h3 class="collection-preview-card__title">
                        <?= e($collection['title']) ?>
                    </h3>
                    <div class="collection-preview-card__meta">
                        <span>📖 <?= (int)$collection['stories_count'] ?> <?= plural((int)$collection['stories_count'], ['статья', 'статьи', 'статей']) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>