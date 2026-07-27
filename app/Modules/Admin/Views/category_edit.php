<?php

/** 
 * @var array $categoryItem 
 * @var W3a\Core\Request $request 
 */
?>

<h1><?= e($title ?? 'Редактирование категории') ?></h1>

<?php if ((int)($categoryItem['tags_count'] ?? 0) > 0): ?>
    <div class="flash-notice">
        <strong>⚠️ Внимание:</strong> В этой категории есть теги. Её нельзя удалить, пока вы не перенесёте их в другую категорию.
    </div>
<?php endif; ?>

<form method="POST" action="<?= route('admin.categories.edit.submit', ['id' => $categoryItem['id'] ?? 0]) ?>">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="name">Название категории <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="name" name="name" required class="form-input-wide"
            value="<?= e($request->getParams('name', $categoryItem['name'] ?? '')) ?>"
            placeholder="Например: Языки программирования">
        <div class="hint">Человекочитаемое название для отображения пользователям.</div>
    </div>

    <div class="form-field-group">
        <label for="slug">Slug (идентификатор) <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="slug" name="slug" required pattern="[a-z0-9\-]+" class="form-input-wide"
            value="<?= e($request->getParams('slug', $categoryItem['slug'] ?? '')) ?>"
            placeholder="Например: languages">
        <div class="hint">Только латиница в нижнем регистре, цифры и дефис. <strong>Изменение slug повлияет на URL категории.</strong></div>
    </div>

    <div class="form-field-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" class="form-input-wide"
            placeholder="Краткое описание назначения категории..."><?= e($request->getParams('description', $categoryItem['description'] ?? '')) ?></textarea>
        <div class="hint">Пояснение для пользователей о содержимом категории (необязательно).</div>
    </div>

    <div class="form-field-group">
        <label for="sort_order">Порядок сортировки</label>
        <input type="number" id="sort_order" name="sort_order" class="form-input-wide"
            value="<?= (int)($request->getParams('sort_order', $categoryItem['sort_order'] ?? 0)) ?>"
            min="0" step="10" style="width: 120px;">
        <div class="hint">Чем меньше число, тем раньше категория появляется в списке. Рекомендуется шаг 10 (10, 20, 30...).</div>
    </div>

    <hr>

    <div class="form-field-group">
        <table class="data" style="max-width: 600px;">
            <tr>
                <td><strong>ID категории:</strong></td>
                <td><?= (int)($categoryItem['id'] ?? 0) ?></td>
            </tr>
            <tr>
                <td><strong>Создана:</strong></td>
                <td><?= e($categoryItem['created_at'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">💾 Сохранить изменения</button>
        <a href="<?= route('admin.categories') ?>" class="button">Отмена</a>
    </div>
</form>