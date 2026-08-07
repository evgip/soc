<h1>Создание публикации</h1>

<p class="hint">Поделитесь интересной ссылкой или начните обсуждение с сообществом.</p>

<?php if (!empty($error)): ?>
<div role="alert" class="alert is-danger">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form action="/stories/create" method="POST" id="story-form">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>
        <?php foreach ($availableTags as $tagItem): ?>
            <?php $isBound = isset($old['tags']) && in_array((int)$tagItem['id'], $old['tags']); ?>
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
            'value' => '', // При создании всегда пусто
            'placeholder' => 'Расскажите подробнее о вашей ссылке или задайте вопрос...',
            'label' => 'Текст обсуждения',
            'hint' => 'Первая строка, оформленная как заголовок (H1 или H2), станет заголовком статьи. Используйте меню блоков (/).',
        ]
    ]);
    ?>

    <div class="form-group">
        <label>
            <input type="checkbox" name="user_is_following" value="1" checked>
            Получать уведомления о новых комментариях к этой истории.
        </label><br>
        <small class="form-text text-muted hint">
            Вы будете получать уведомления о всех новых комментариях в этой истории.
        </small>
    </div>

    <div class="form-actions v-center">
        <button type="submit">Опубликовать</button>
        <a href="/">Отмена</a>
    </div>
</form>
