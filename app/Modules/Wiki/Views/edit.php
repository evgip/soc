<h1>Редактирование wiki страницы</h1>

<p class="hint">
    Вы редактируете страницу <strong><?= e($page['title']) ?></strong> для тега <strong>#<?= e($tag['name']) ?></strong>.
</p>

<!-- Блок ошибок валидации -->
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

<form action="/t/<?= e($tag['slug']) ?>/wiki/<?= $page['id'] ?>/update" method="POST">
    <?= csrf_field() ?>

    <!-- Подключаем ту же самую универсальную форму -->
    <?php include __DIR__ . '/_form.php'; ?>

    <div class="form-actions">
        <button type="submit">Сохранить изменения</button>
        <a href="/t/<?= e($tag['slug']) ?>/wiki/<?= e($page['slug']) ?>">Отмена</a>
    </div>
</form>