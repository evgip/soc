<?php
$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$canUserDownvote = $canUserDownvote ?? false;
$voteModel = $voteModel ?? null;
?>

<?php
$currentSort = $sort ?? 'hot';
$sortLinks = [
    'hot' => ['url' => '/?sort=hot',         'label' => 'Hot'],
    'new' => ['url' => '/?sort=new',         'label' => 'New'],
    'top' => ['url' => '/?sort=top',         'label' => 'Top'],
];
?>

<nav class="nav br-none">
    <?php foreach ($sortLinks as $key => $link): ?>
        <a href="<?= $link['url'] ?>"
            class="<?= $currentSort === $key ? 'is-active' : '' ?>">
            <?= $link['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($tagInfo['slug'])): ?>
	<div class="hint mb1">
		Статьи, <a href="/tags#<?= e($tagInfo['slug']); ?>">помеченные</a> как <a href="<?= route('tags.filter', ['tagslug' => $tagInfo['slug']]) ?>" class="tag tag-<?= e($tagInfo['slug']); ?>"><?= e($tagInfo['name']); ?></a>. 
		<br>
		<?php if (!empty($primaryWikiPage['title'])): ?>
			<?php if ($wikiPagesCount > 0 || $primaryWikiPage): ?>
				Wiki статья привязанная к тегу   <?= e($tagInfo['name']); ?>: <a href="/t/<?= e($tagInfo['slug']) ?>/wiki/<?= e($primaryWikiPage['slug']) ?>"><?= e($primaryWikiPage['title']) ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if (!empty($author)): ?>
<div class="hint mb1">
    Публикации пользователя: <?= e($author) ?>
	<br>
    <a href="/">× Сбросить фильтр</a>
</div>
<?php endif; ?>

<?php if (!empty($domain)): ?>
<div class="hint mb1">
    Публикации по домену: <?= e($domain) ?>
	<br>
    <a href="/">× Сбросить фильтр</a>
</div>
<?php endif; ?>

<?php if (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php
            $isStoryDeleted = !empty($story['deleted_at']);

            $fullHtml = markdown_comment($story['description']);
            $needsTruncation = needsTruncation($fullHtml, 300);

            $newCount = $newCommentsMap[$story['id']] ?? 0;
            ?>

            <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

                <?php partial('Votes::_voters', [
                    'type' => 'story',
                    'id' => (int)$story['id'],
                    'score' => (int)$story['score'],
                    'currentVoteState' => $currentVotes[$story['id']] ?? null,
                    'canDownvote' => $canUserDownvote,
                    'isLoggedIn' => $currentUserId > 0,
                    'contentOwnerId' => (int)$story['user_id'],
                ]); ?>

                <div class="story_liner">
                    <div class="link">
                        <?php if ($isStoryDeleted): ?>
                            <em>[Удалена модератором]</em>
                        <?php endif; ?>

                        <?php
                        $isExternal = !empty($story['url']);
                        $targetUrl = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
                        ?>

                        <a class="title" href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= e($story['title']) ?>
                        </a>

                        <?php if ($isExternal): ?>
                            <?php
                            $domainHost = !empty($story['url']) ? parse_url($story['url'], PHP_URL_HOST) : null;
                            if ($domainHost):
                                $isBannedDomain = false;
                                if (isset($bannedDomainsCache)) {
                                    $isBannedDomain = in_array(strtolower($domainHost), $bannedDomainsCache, true);
                                }
                            ?>
                                <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>"
                                    class="domain <?= $isBannedDomain ? 'domain-banned' : '' ?>"
                                    title="<?= $isBannedDomain ? '⚠ Домен заблокирован модераторами' : '' ?>">
                                    <?= e($domainHost) ?>
                                    <?php if ($isBannedDomain): ?>
                                        🚫
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($story['tags'])): ?>
                            <span class="tags">
                                <?php foreach ($story['tags_with_names'] as $tagData): ?>
                                    <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag tag-<?= e($tagData['slug']); ?>"><?= e($tagData['name']) ?></a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="story_content">
                        <?php if ($needsTruncation): ?>
                            <details>
                                <summary>
                                    <?= truncateDescription($fullHtml, 150) ?>
                                </summary>
                                <div class="full-content">
                                    <?= $fullHtml ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <?= $fullHtml ?>
                        <?php endif; ?>
                    </div>

                    <?php partial('Users::_story_meta', [
                        'story' => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin,
                        'newCount' => $newCount,
                    ]); ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>

<?php else: ?>
    <p class="hint">Лента историй пока пуста.</p>
<?php endif; ?>