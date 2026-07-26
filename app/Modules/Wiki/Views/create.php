<h1>Создание wiki страницы</h1>

<p class="hint">
    Создайте документацию для тега <strong>#<?= e($tag['name']) ?></strong>, чтобы помочь другим пользователям лучше понимать его.
</p>

<?php if (!empty($error)): ?>
    <div role="alert" class="alert is-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/t/<?= e($tag['slug']) ?>/wiki/store" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label><strong>Тег</strong></label>
        <p class="hint">
            Wiki страница будет привязана к тегу: <strong>#<?= e($tag['name']) ?></strong>
        </p>
    </div>

    <?php include __DIR__ . '/_form.php'; ?>

    <div class="form-actions">
        <button type="submit">Создать страницу</button>
        <a href="/t/<?= e($tag['slug']) ?>/wiki">Отмена</a>
    </div>
</form>