<?php
// Получаем список категорий
$categoryModel = container(App\Modules\Tags\Models\Category::class);
$categories = $categoryModel->getAllOrdered();
?>

<?php
/** 
 * @var array $tagItem 
 * @var array $categories 
 * @var W3a\Core\Http\Request $request 
 */
?>

<h1><?= e($title ?? 'Редактирование тега') ?></h1>

<form method="POST" action="<?= route('admin.tags.edit.submit', ['id' => $tagItem['id'] ?? 0]) ?>">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="name">Название тега <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="name" name="name" required class="form-input-wide"
            value="<?= e($request->getParams('name', $tagItem['name'] ?? '')) ?>"
            placeholder="Например: php">
        <div class="hint">Только латиница в нижнем регистре, цифры и дефис.</div>
    </div>

    <div class="form-field-group">
        <label for="slug">URL тега (slug) <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="text" id="slug" name="slug" required pattern="[a-z0-9\-]+" class="form-input-wide"
            value="<?= e($request->getParams('slug', $tagItem['slug'] ?? '')) ?>"
            placeholder="Например: php">
        <div class="hint">Только латиница в нижнем регистре, цифры и дефис. <strong>Изменение повлияет на URL тега.</strong></div>
    </div>

    <div class="form-field-group">
        <label for="category_id">Категория <span class="form-field-hint-inline">(обязательно)</span></label>
        <select id="category_id" name="category_id" required class="form-input-wide">
            <option value="">— Выберите категорию —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"
                    <?= ((int)($request->getParams('category_id', $tagItem['category_id'] ?? 0)) === (int)$cat['id']) ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?> (<?= e($cat['slug']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="hint">Определяет колонку на странице общего каталога тегов.</div>
    </div>

    <div class="form-field-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" class="form-input-wide"
            placeholder="Краткое описание тега..."><?= e($request->getParams('description', $tagItem['description'] ?? '')) ?></textarea>
        <div class="hint">Пояснение для пользователей о содержимом тега (необязательно).</div>
    </div>

    <div class="form-field-group">
        <label for="hotness_mod">hotness_mod</label>
        <input type="text" id="hotness_mod" name="hotness_mod" class="form-input-wide"
            value="<?= e($request->getParams('hotness_mod', $tagItem['hotness_mod'] ?? '')) ?>"
            placeholder="Например: php">
        <div class="hint">0.5 - в топе дольше, -0.2 - для редких случаев, -5.0 - мгновенно уронит истории. <strong>Изменение повлияет на вес историй.</strong></div>
    </div>

    <div class="form-field-group">
        <label>
            <input type="checkbox" name="is_media" value="1"
                <?= ((string)$request->getParams('is_media', (string)($tagItem['is_media'] ?? '0')) === '1') ? 'checked' : '' ?>>
            Это медиа-тег (видео, подкасты, демонстрации проектов)
        </label>
        <div class="hint">Медиа-теги отображаются со специальным значком в интерфейсе сайта.</div>
    </div>

    <hr>

    <div class="form-field-group">
        <table class="data" style="max-width: 600px;">
            <tr>
                <td><strong>ID тега:</strong></td>
                <td><?= (int)($tagItem['id'] ?? 0) ?></td>
            </tr>
            <tr>
                <td><strong>Создан:</strong></td>
                <td><?= e($tagItem['created_at'] ?? '—') ?></td>
            </tr>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">💾 Сохранить изменения</button>
        <a href="<?= route('admin.tags') ?>" class="button">Отмена</a>
    </div>
</form>