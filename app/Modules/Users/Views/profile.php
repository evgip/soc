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
            <span><strong><?= (int)$storiesCount ?></strong> публикаций</span>
            <span class="divider">·</span>
            <span><strong><?= (int)$commentsCount ?></strong> комментариев</span>
            <span class="divider">·</span>
            <?php
            $karmaClass = $userKarma > 0 ? 'text-positive' : ($userKarma < 0 ? 'text-negative' : 'text-muted');
            ?>
            <span class="<?= $karmaClass ?>"><strong><?= $userKarma > 0 ? '+' : '' ?><?= (int)$userKarma ?></strong> кармы</span>
        </div>

      
        <?php if (!$isOwnProfile && $currentUserId > 0): ?>
            <div class="profile-actions flex gap mt2">
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
            <div class="mod-actions mt2">
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
                <?php partial('Stories::_story_item', [
                    'story' => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin' => \W3a\Core\Auth\Auth::isModerator(),
                    'canUserDownvote' => true,
                    'currentVotes' => [], // В профиле обычно не подгружают голоса для всех статей сразу для экономии, или подгрузите из $feed
                    'newCommentsMap' => [],
                    'hideAuthor' => true, // скрываем имя и аватар, так как мы уже в профиле автора
                ]); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt3">
                <?= pagination($currentPage, $totalPages) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>