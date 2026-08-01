<h1>Создание wiki страницы</h1>

<p class="hint">
    Создайте документацию для тега <strong>#<?= e($tag['name']) ?></strong>, чтобы помочь другим пользователям лучше понимать его.
</p>

<!-- Блок ошибок валидации (если он не выводится глобально в вашем layout.php) -->
<?php if ($errors->allErrors()): ?>
    <div role="alert" class="alert is-danger">
        <strong>Исправьте ошибки в форме:</strong>
        <ul class="validation-errors-list">
            <?php foreach ($errors->allErrors() as $fieldErrors): ?>
                <?php foreach ($fieldErrors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
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

    <!-- Подключаем универсальную форму -->
    <?php include __DIR__ . '/_form.php'; ?>

    <div class="form-actions">
        <button type="submit">Создать страницу</button>
        <a href="/t/<?= e($tag['slug']) ?>/wiki">Отмена</a>
    </div>
</form>