<h1>Создание публикации</h1>

<p class="hint">
Поделитесь интересной ссылкой или начните обсуждение с сообществом.
</p>

<?php if (!empty($error)): ?>
<div role="alert" class="alert is-danger">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form action="/stories/create" method="POST" id="story-form">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="story-title"><strong>Заголовок</strong></label>
        <input type="text" id="story-title" name="title"
               value="<?= e($old['title'] ?? '') ?>"
               required placeholder="Введите заголовок публикации"
               class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label for="story-url">
            <strong>Ссылка (URL)</strong>
            <span class="form-field-hint-inline">— необязательно</span>
        </label>
        <div>
            <input type="url" id="story-url" name="url"
                   value="<?= e($old['url'] ?? '') ?>"
                   placeholder="https://example.com/article"
                   class="form-input-wide">
            <button type="button" id="fetch-title-btn" class="btn-secondary">
                Извлечь заголовок
            </button>
        </div>
        <div id="fetch-status" class="red"></div>
    </div>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>
        <?php foreach ($availableTags as $tagItem): ?>
            <?php
            $isBound = isset($old['tags']) && in_array((int)$tagItem['id'], $old['tags']);
            ?>
            <div class="tag">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>"
                    <?= $isBound ? 'checked' : '' ?>>
                <span><?= e($tagItem['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Подключаем универсальный Markdown-редактор через partial()
    partial('Common::_markdown_editor', [
        'editor' => [
            'name' => 'description',
            'value' => $old['description'] ?? '',
            'placeholder' => 'Сопроводительный текст, комментарии или дополнительный контекст...',
            'rows' => 10,
            'textarea_id' => 'story-description',
            'preview_url' => '/stories/preview',
            'allow_images' => true,
            'label' => 'Текст обсуждения',
            'hint' => 'Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`',
        ]
    ]);
    ?>

    <div class="form-group">
        <label>
            <input type="checkbox" name="user_is_following" value="1"
                <?= !empty($story['user_is_following']) ? 'checked' : '' ?>>
            Получать уведомления о новых комментариях к этой истории.
        </label><br>
        <small class="form-text text-muted hint">
            Вы будете получать уведомления о всех новых комментариях в этой истории.
        </small>
    </div>

    <div class="form-actions">
        <button type="submit">Опубликовать</button>
        <a href="/">Отмена</a>
    </div>
</form>

<script nonce="<?= csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('story-url');
    const titleInput = document.getElementById('story-title');
    const fetchBtn = document.getElementById('fetch-title-btn');
    const statusDiv = document.getElementById('fetch-status');

    // Функция извлечения заголовка
    function fetchTitle() {
        const url = urlInput.value.trim();
        
        if (!url) {
            statusDiv.textContent = 'Введите URL';
            statusDiv.style.color = '#c00';
            return;
        }

        // Валидация URL
        try {
            new URL(url);
        } catch (e) {
            statusDiv.textContent = 'Некорректный URL';
            statusDiv.style.color = '#c00';
            return;
        }

        // Показываем статус загрузки
        fetchBtn.disabled = true;
        fetchBtn.textContent = 'Загрузка...';
        statusDiv.textContent = 'Извлекаем заголовок...';
        statusDiv.style.color = '#666';

        // ✅ Используем POST запрос с CSRF-защитой
        const formData = new FormData();
        formData.append('url', url);

        fetch('/stories/fetch-url-title', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
            // headers (X-XSRF-TOKEN, X-Requested-With) добавляются автоматически перехватчиком из core_utils.js
        })
        .then(response => {
            console.log('Response status:', response.status);
            
            if (response.status === 419) {
                throw new Error('Сессия истекла. Обновите страницу.');
            }
            
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'HTTP error! status: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                statusDiv.textContent = data.error;
                statusDiv.style.color = '#c00';
                return;
            }

            if (data.title) {
                titleInput.value = data.title;
                statusDiv.textContent = '✓ Заголовок извлечен';
                statusDiv.style.color = '#0a0';
                
                // Обновляем URL если найден canonical
                if (data.url && data.url !== url) {
                    urlInput.value = data.url;
                }
            } else {
                statusDiv.textContent = 'Не удалось извлечь заголовок';
                statusDiv.style.color = '#c00';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.textContent = 'Ошибка: ' + error.message;
            statusDiv.style.color = '#c00';
        })
        .finally(() => {
            fetchBtn.disabled = false;
            fetchBtn.textContent = 'Извлечь заголовок';
        });
    }

    // Обработчик кнопки
    fetchBtn.addEventListener('click', fetchTitle);

    // Автоматическое извлечение при потере фокуса на поле URL
    let blurTimeout;
    urlInput.addEventListener('blur', function() {
        const url = this.value.trim();
        if (url && !titleInput.value.trim()) {
            blurTimeout = setTimeout(() => {
                if (url === urlInput.value.trim() && !titleInput.value.trim()) {
                    fetchTitle();
                }
            }, 800);
        }
    });

    urlInput.addEventListener('focus', function() {
        if (blurTimeout) {
            clearTimeout(blurTimeout);
        }
    });

    urlInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            fetchTitle();
        }
    });
});
</script>