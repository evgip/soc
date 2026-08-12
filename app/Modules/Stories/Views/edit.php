<h1>Редактирование публикации</h1>

<p class="hint">Вы можете откорректировать заголовок, описание и изменить привязанные к теме теги.</p>

<?php if (!empty($error)): ?>
    <div role="alert" class="alert is-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/stories/<?= (int)$story['id'] ?>/edit" method="POST" id="story-form">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>

        <?php foreach ($availableTags as $tagItem): ?>
            <?php $isBound = in_array((int)$tagItem['id'], $activeTagIds); ?>
            <div class="tag-checkbox">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>" <?= $isBound ? 'checked' : '' ?>>
                <span><?= e($tagItem['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    partial('Common::_editor', [
        'editor' => [
            'name' => 'description',
            // Берем JSON, если он есть. Иначе fallback на старый текст (на случай, если статья не мигрирована)
            'value' => $story['description_json'] ?: $story['description'],
            'placeholder' => 'Отредактируйте текст публикации...',
            'label' => 'Текст обсуждения',
            'hint' => 'Первая строка, оформленная как заголовок (H1 или H2), станет заголовком статьи. Используйте меню блоков (/).',
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

<?php if ($story['has_paywall'] && $story['user_id'] === $userContext['id']): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5>🔗 Дружеские ссылки (Friend Links)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Создайте специальную ссылку, чтобы поделиться статьей с друзьями бесплатно
        </p>
        
        <button id="create-friend-link" class="btn btn-primary mb-3">
            Создать новую ссылку
        </button>
        
        <div id="friend-links-list">
            <!-- Здесь будут отображаться существующие ссылки -->
        </div>
        
        <div id="new-link-container" class="d-none mt-3">
            <label>Новая ссылка создана:</label>
            <div class="input-group">
                <input type="text" id="new-link-url" class="form-control" readonly>
                <button class="btn btn-outline-secondary" onclick="copyLink()">
                    Копировать
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.getElementById('create-friend-link').addEventListener('click', async () => {
    const response = await fetch('/stories/<?= $story['id'] ?>/friend-link', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
        }
    });
    
    const data = await response.json();
    
    if (data.success) {
        const container = document.getElementById('new-link-container');
        const input = document.getElementById('new-link-url');
        
        input.value = data.url;
        container.classList.remove('d-none');
        
        // Обновить список ссылок
        loadFriendLinks();
    }
});

async function loadFriendLinks() {
    // Загрузить и отобразить список существующих ссылок
    // с кнопками "Копировать" и "Удалить"
}

function copyLink() {
    const input = document.getElementById('new-link-url');
    input.select();
    document.execCommand('copy');
    alert('Ссылка скопирована!');
}
</script>
<?php endif; ?>




    <?php $isDraft = ($story['status'] ?? 'published') === 'draft'; ?>

    <div class="form-actions v-center">
        <?php if ($isDraft): ?>
            <button type="submit" name="action" value="publish">Опубликовать</button>
            <button type="submit" name="action" value="draft" class="btn btn--secondary">Сохранить черновик</button>
        <?php else: ?>
            <button type="submit" name="action" value="save">Сохранить изменения</button>
        <?php endif; ?>
        <a href="<?= $isDraft ? '/drafts' : route('story.show', ['id' => $story['id']]) ?>">Отмена</a>
    </div>
</form>
