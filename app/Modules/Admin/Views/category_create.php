<?php

/** 
 * @var W3a\Core\Http\Request $request 
 */
?>

<h1><?= e($title ?? 'Создание новой категории') ?></h1>

<p class="hint">
    Создание новой категории для группировки тегов. Категория определяет колонку на странице общего каталога тегов.
</p>

<form method="POST" action="<?= route('admin.categories.create.submit') ?>">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="name">Название категории <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="name" name="name" required class="form-input-wide"
            value="<?= e($request->getParams('name', '')) ?>"
            placeholder="Например: Языки программирования">
        <div class="hint">Человекочитаемое название для отображения пользователям.</div>
    </div>

    <div class="form-field-group">
        <label for="slug">Slug (идентификатор) <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="slug" name="slug" required pattern="[a-z0-9\-]+" class="form-input-wide"
            value="<?= e($request->getParams('slug', '')) ?>"
            placeholder="Например: languages">
        <div class="hint">Только латиница в нижнем регистре, цифры и дефис. Используется в URL.</div>
    </div>

    <div class="form-field-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" class="form-input-wide"
            placeholder="Краткое описание назначения категории..."><?= e($request->getParams('description', '')) ?></textarea>
        <div class="hint">Пояснение для пользователей о содержимом категории (необязательно).</div>
    </div>

    <div class="form-field-group">
        <label for="sort_order">Порядок сортировки</label>
        <input type="number" id="sort_order" name="sort_order" class="form-input-wide"
            value="<?= (int)($request->getParams('sort_order', 0)) ?>"
            min="0" step="10" style="width: 120px;">
        <div class="hint">Чем меньше число, тем раньше категория появляется в списке. Рекомендуется шаг 10 (10, 20, 30...).</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">💾 Создать категорию</button>
        <a href="<?= route('admin.categories') ?>" class="button">Отмена</a>
    </div>
</form>