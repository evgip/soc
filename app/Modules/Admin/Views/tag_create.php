<?php
// Получаем список категорий
$categoryModel = container(App\Modules\Tags\Models\Category::class);
$categoriesList = $categoryModel->getAllOrdered();
?>

<h3>✨ Добавление новой темы/тега</h3>
<p class="admin-subtitle-desc">Создание нового тега для классификации обсуждений. Укажите имя тега в нижнем регистре и выберите подходящую категорию.</p>

<form action="<?= route('admin.tags.create.submit') ?>" method="POST" class="admin-form-container">
	<?= csrf_field() ?>

	<div class="form-field-group">
		<label for="name">Название тега <span class="form-field-hint-inline">(обязательно)</span></label>
		<input type="text" id="name" name="name" required class="form-input-wide"
			placeholder="Например: видео">
		<div class="hint">Только латиница в нижнем регистре, цифры и дефис.</div>
	</div>


	<div class="form-field-group">
		<label>URL тега (slug):</label>
		<input type="text" name="slug" required placeholder="например: video">
		<small class="text-muted">Только латиница в нижнем регистре, без пробелов.</small>
	</div>

	<div class="form-field-group">
		<label for="category_id">Категория:</label>
		<select id="category_id" name="category_id" required>
			<option value="">— Выберите категорию —</option>
			<?php foreach ($categoriesList as $cat): ?>
				<option value="<?= (int)$cat['id'] ?>"
					<?= ($request->getParams('category_id', $tagItem['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
					<?= e($cat['name']) ?> (<?= e($cat['slug']) ?>)
				</option>
			<?php endforeach; ?>
		</select>
		<small>Определяет колонку на странице общего каталога тегов.</small>
	</div>

	<div class="form-field-group">
		<label for="description">Описание</label>
		<textarea id="description" name="description" rows="3" class="form-input-wide"
			placeholder="Краткое описание тега..."></textarea>
		<div class="hint">Пояснение для пользователей о содержимом тега (необязательно).</div>
	</div>

	<!-- CLEAN CLASS-DRIVEN CHECKBOX ROW -->
	<div class="form-field-groupw">
		<input type="checkbox" name="is_media" id="chk-is-media">
		<label Security-policy-label-target for="chk-is-media">Этот тег обозначает Медиа-контент (видео, подкаст, pdf)</label>
	</div>

	<div class="form-actions">
		<button type="submit" class="btn-primary">💾 Создать тег</button>
		<a href="/admin/tags" class="button">Отмена</a>
	</div>
</form>