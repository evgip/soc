<?php
declare(strict_types=1);

/**
 * @var array|null $collection (null для создания)
 * @var string $formAction
 * @var string $submitLabel
 * @var string|null $coverUrl
 */

$collection = $collection ?? null;
$coverUrl = $coverUrl ?? null;
?>

<div class="container container-narrow">
    <h1><?= $collection ? 'Редактирование коллекции' : 'Новая коллекция' ?></h1>

    <form action="<?= $formAction ?>" method="POST" enctype="multipart/form-data" class="collection-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="collection-title">Название *</label>
            <input type="text" 
                   id="collection-title" 
                   name="title" 
                   value="<?= e($collection['title'] ?? '') ?>" 
                   required 
                   maxlength="200"
                   placeholder="Например: Песни Миларепы">
        </div>

        <div class="form-field-group">
            <label for="collection-description">Описание</label>
            <textarea id="collection-description" 
                      name="description" 
                      rows="4" 
                      maxlength="1000"
                      placeholder="Краткое описание коллекции..."><?= e($collection['description'] ?? '') ?></textarea>
        </div>

        <div class="form-field-group">
            <label><strong>Обложка коллекции</strong></label>
            <div class="cover-upload-container">
                <!-- Превью -->
                <div class="cover-preview" id="cover-preview">
                    <?php if ($coverUrl): ?>
                        <img src="<?= e($coverUrl) ?>" alt="Обложка" class="cover-preview__image">
                        <label class="cover-preview__delete">
                            <input type="checkbox" name="delete_cover" value="1" style="display:none;">
                            <span class="cover-preview__delete-btn">🗑️ Удалить обложку</span>
                        </label>
                    <?php else: ?>
                        <div class="cover-preview__placeholder">
                            <span>📚</span>
                            <p>Обложка не установлена</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Input для загрузки -->
                <div class="cover-upload__controls">
                    <input type="file" 
                           name="cover_file" 
                           accept="image/jpeg,image/png,image/gif,image/webp" 
                           id="cover-file-input"
                           class="form-input-file">
                    <p class="hint">
                        Рекомендуемый размер: <strong>1200×630px</strong> или <strong>16:9</strong>.<br>
                        Форматы: JPG, PNG, GIF, WebP. Максимум 5 МБ.
                    </p>
                </div>
            </div>
        </div>

        <div class="form-field-group">
            <label class="checkbox-label">
                <input type="checkbox" 
                       name="is_public" 
                       value="1"
                       <?= ($collection === null || !empty($collection['is_public'])) ? 'checked' : '' ?>>
                Публичная коллекция (видна всем)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-pill"><?= $submitLabel ?></button>
            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-pill">Отмена</a>
        </div>
    </form>
</div>

<script nonce="<?= csp_nonce(); ?>">
(function() {
    'use strict';

    const fileInput = document.getElementById('cover-file-input');
    const preview = document.getElementById('cover-preview');
    if (!fileInput || !preview) return;

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            // Скрываем блок "удалить" при выборе нового файла
            const deleteLabel = preview.querySelector('.cover-preview__delete');
            if (deleteLabel) deleteLabel.style.display = 'none';

            // Показываем новое превью
            let img = preview.querySelector('.cover-preview__image');
            if (!img) {
                // Если нет img (был placeholder) — заменяем содержимое
                preview.innerHTML = '';
                img = document.createElement('img');
                img.className = 'cover-preview__image';
                img.alt = 'Превью обложки';
                preview.appendChild(img);
            }
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Обработка чекбокса "Удалить обложку"
    const deleteCheckbox = preview.querySelector('.cover-preview__delete input[type="checkbox"]');
    const deleteBtn = preview.querySelector('.cover-preview__delete-btn');
    if (deleteCheckbox && deleteBtn) {
        deleteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            deleteCheckbox.checked = !deleteCheckbox.checked;
            deleteBtn.style.opacity = deleteCheckbox.checked ? '0.5' : '1';
            deleteBtn.textContent = deleteCheckbox.checked ? '✓ Обложка будет удалена' : '🗑️ Удалить обложку';
        });
    }
})();
</script>