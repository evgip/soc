<div class="form-field-group">
    <label for="wiki-title"><strong>Заголовок</strong></label>
    <input type="text" id="wiki-title" name="title"
        value="<?= e($errors->getOld('title', $page['title'] ?? '')) ?>"
        required placeholder="Введите заголовок страницы"
        class="form-input-wide <?= $errors->hasError('title') ? 'is-danger' : '' ?>">
    <?php if ($errors->hasError('title')): ?>
        <small class="form-error-text"><?= $errors->firstError('title') ?></small>
    <?php endif; ?>
</div>

<div class="form-field-group">
    <label for="wiki-slug">
        <strong>URL (slug)</strong>
        <span class="form-field-hint-inline">— необязательно</span>
    </label>
    <input type="text" id="wiki-slug" name="slug"
        value="<?= e($errors->getOld('slug', $page['slug'] ?? '')) ?>"
        placeholder="auto-generated-slug"
        pattern="[a-z0-9\-]+"
        class="form-input-wide <?= $errors->hasError('slug') ? 'is-danger' : '' ?>">
    <small class="form-text text-muted">
        Только латинские буквы, цифры и дефисы. Если оставить пустым — будет сгенерирован автоматически из заголовка.
    </small>
    <?php if ($errors->hasError('slug')): ?>
        <small class="form-error-text"><?= $errors->firstError('slug') ?></small>
    <?php endif; ?>
</div>

<div class="form-field-group">
    <label for="wiki-content"><strong>Содержимое</strong></label>
    <p class="hint">Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`, списки</p>
    <textarea id="wiki-content" name="content" rows="15"
        required placeholder="Текст wiki страницы..."
        class="<?= $errors->hasError('content') ? 'is-danger' : '' ?>"><?= e($errors->getOld('content', $page['content'] ?? '')) ?></textarea>
    <?php if ($errors->hasError('content')): ?>
        <small class="form-error-text"><?= $errors->firstError('content') ?></small>
    <?php endif; ?>
</div>

<div class="form-field-group">
    <label>
        <input type="checkbox" name="is_primary" value="1"
            <?= $errors->getOld('is_primary', $page['is_primary'] ?? 0) ? 'checked' : '' ?>>
        <strong>Сделать основной страницей тега</strong>
    </label>
    <small class="form-text text-muted">
        Основная страница будет отображаться первой в списке wiki и показываться как главная документация тега.
    </small>
</div>

<!-- Это поле показываем ТОЛЬКО если существует $page (то есть мы в режиме редактирования) -->
<?php if (isset($page)): ?>
    <div class="form-field-group">
        <label for="wiki-edit-summary">
            <strong>Описание изменений</strong>
            <span class="form-field-hint-inline">— необязательно</span>
        </label>
        <input type="text" id="wiki-edit-summary" name="edit_summary"
            value="<?= e($errors->getOld('edit_summary', '')) ?>"
            placeholder="Кратко опишите что вы изменили..."
            class="form-input-wide">
        <small class="form-text text-muted">
            Это поможет другим пользователям понять, что было изменено.
        </small>
    </div>
<?php endif; ?>


<script nonce="<?= csp_nonce(); ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const titleInput = document.getElementById('wiki-title');
        const slugInput = document.getElementById('wiki-slug');

        // Флаг, указывающий что пользователь редактировал slug вручную
        let slugManuallyEdited = false;

        // Если slug уже заполнен (режим редактирования) - считаем что он редактировался
        if (slugInput.value.trim() !== '') {
            slugManuallyEdited = true;
        }

        // Отслеживаем ручное редактирование slug
        slugInput.addEventListener('input', function() {
            slugManuallyEdited = true;
        });

        // Автогенерация slug из заголовка
        titleInput.addEventListener('input', function() {
            if (slugManuallyEdited) return;

            const title = this.value;
            const slug = transliterate(title);
            slugInput.value = slug;
        });

        // Функция транслитерации
        function transliterate(text) {
            const map = {
                'а': 'a',
                'б': 'b',
                'в': 'v',
                'г': 'g',
                'д': 'd',
                'е': 'e',
                'ё': 'yo',
                'ж': 'zh',
                'з': 'z',
                'и': 'i',
                'й': 'y',
                'к': 'k',
                'л': 'l',
                'м': 'm',
                'н': 'n',
                'о': 'o',
                'п': 'p',
                'р': 'r',
                'с': 's',
                'т': 't',
                'у': 'u',
                'ф': 'f',
                'х': 'kh',
                'ц': 'ts',
                'ч': 'ch',
                'ш': 'sh',
                'щ': 'sch',
                'ъ': '',
                'ы': 'y',
                'ь': '',
                'э': 'e',
                'ю': 'yu',
                'я': 'ya',
                ' ': '-',
                '_': '-',
                '.': '-'
            };

            return text
                .toLowerCase()
                .split('')
                .map(char => map[char] || char)
                .join('')
                .replace(/[^a-z0-9\-]/g, '')
                .replace(/-+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 200);
        }
    });
</script>