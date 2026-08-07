<?php
/**
 * Wiki index - список wiki страниц тега
 * Дизайн в стиле Stories index
 */
?>

<?= $breadcrumbs ?>

<div class="hint">
    Wiki страницы тега <a href="/t/<?= e($tag['slug']) ?>" class="tag tag-<?= e($tag['slug']) ?>"><?= e($tag['name']) ?></a>.
    <?php if (!empty($tag['description'])): ?>
        <br><?= e($tag['description']) ?>
    <?php endif; ?>
</div>

<div class="form-actions">
    <a href="<?= route('wiki.tag.create', ['tagslug' => $tag['slug']]) ?>" class="btn">
        ➕ Создать страницу
    </a>
    
    <form action="<?= route('wiki.tag.search', ['tagslug' => $tag['slug']]) ?>" method="GET" class="inline-form">
        <input type="text" name="q" placeholder="Поиск по wiki..." required>
        <button type="submit">🔍 Найти</button>
    </form>
</div>

<?php if (!empty($primaryPage) && is_array($primaryPage)): ?>
    <h2>📖 Основная документация</h2>
    <ol class="stories">
        <?= \App\Modules\Wiki\Components\WikiCard::render($primaryPage, $tag, true) ?>
    </ol>
<?php endif; ?>

<?php
// Безопасная фильтрация остальных страниц
$primaryId = isset($primaryPage['id']) ? (int)$primaryPage['id'] : 0;
$otherPages = array_filter($pages, function($p) use ($primaryId) {
    return is_array($p) && !empty($p) && (int)($p['id'] ?? 0) !== $primaryId;
});
?>

<?php if (!empty($otherPages)): ?>
    <h2>📄 Все страницы</h2>
    <ol class="stories">
        <?php foreach ($otherPages as $page): ?>
            <?= \App\Modules\Wiki\Components\WikiCard::render($page, $tag, false, $canSeeDeleted, $request) ?>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if (empty($pages)): ?>
    <div class="hint">
        <p>Для этого тега еще нет wiki страниц.</p>
        <p><a href="<?= route('wiki.tag.create', ['tagslug' => $tag['slug']]) ?>">Создайте первую страницу!</a></p>
    </div>
<?php endif; ?>