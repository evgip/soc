<h1>Редактирование wiki страницы</h1>

<p class="hint">
    Вы редактируете страницу <strong><?= e($page['title']) ?></strong> для тега <strong>#<?= e($tag['name']) ?></strong>.
</p>

<?php if (!empty($error)): ?>
    <div role="alert" class="alert is-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/t/<?= e($tag['slug']) ?>/wiki/<?= $page['id'] ?>/update" method="POST">
    <?= csrf_field() ?>

    <?php $isEdit = true; ?>
    <?php include __DIR__ . '/_form.php'; ?>

    <div class="form-actions">
        <button type="submit">Сохранить изменения</button>
        <a href="/t/<?= e($tag['slug']) ?>/wiki/<?= e($page['slug']) ?>">Отмена</a>
    </div>
</form>