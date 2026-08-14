<?php
declare(strict_types=1);

/**
 * @var array $profileUser
 * @var array $collections
 * @var bool $isOwner
 */
?>

<div class="container">
    <div class="collections-header">
        <div>
            <h1>Коллекции <?= e($profileUser['username']) ?></h1>
            <p class="hint"><?= count($collections) ?> <?= plural(count($collections), ['коллекция', 'коллекции', 'коллекций']) ?></p>
        </div>

        <?php if ($isOwner): ?>
            <a href="<?= route('collections.create') ?>" class="btn btn-primary btn-pill">
                + Новая коллекция
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($collections)): ?>
        <div class="empty-state">
            <h2>Пока нет коллекций</h2>
            <p>Коллекции позволяют объединять статьи в серии с общим сюжетом.</p>
            <?php if ($isOwner): ?>
                <div class="empty-state__actions">
                    <a href="<?= route('collections.create') ?>" class="btn btn-primary btn-pill">
                        Создать первую коллекцию
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="collections-grid">
            <?php foreach ($collections as $collection): ?>
                <?php partial('Collections::_card', [
                    'collection' => $collection,
                    'profileUser' => $profileUser,
                    'isOwner' => $isOwner,
                ]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>