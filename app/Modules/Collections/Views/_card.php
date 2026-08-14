<?php
declare(strict_types=1);

/**
 * @var array $collection
 * @var array $profileUser
 * @var bool $isOwner
 */

$collectionUrl = '/collections/' . e($profileUser['username']) . '/' . e($collection['slug']);
?>

<div class="collection-card">
	<?php if (!empty($collection['cover_url'])): ?>
		<a href="<?= $collectionUrl ?>" class="collection-card__cover">
			<img src="<?= e($collection['cover_url']) ?>" alt="<?= e($collection['title']) ?>" loading="lazy">
		</a>
	<?php else: ?>
        <a href="<?= $collectionUrl ?>" class="collection-card__cover collection-card__cover--placeholder">
            <span>📚</span>
        </a>
    <?php endif; ?>

    <div class="collection-card__body">
        <h3 class="collection-card__title">
            <a href="<?= $collectionUrl ?>"><?= e($collection['title']) ?></a>
        </h3>

        <?php if (!empty($collection['description'])): ?>
            <p class="collection-card__description">
                <?= e(mb_substr($collection['description'], 0, 120)) ?><?= mb_strlen($collection['description']) > 120 ? '...' : '' ?>
            </p>
        <?php endif; ?>

        <div class="collection-card__meta">
            <span>📖 <?= (int) $collection['stories_count'] ?> <?= plural((int) $collection['stories_count'], ['статья', 'статьи', 'статей']) ?></span>
            <?php if (empty($collection['is_public'])): ?>
                <span class="collection-card__private">🔒 Приватная</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isOwner): ?>
        <div class="collection-card__actions">
            <a href="<?= route('collections.edit', ['id' => $collection['id']]) ?>" class="btn btn-sm btn-outline-secondary">
                ✏️ Редактировать
            </a>
        </div>
    <?php endif; ?>
</div>