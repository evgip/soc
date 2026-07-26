<dialog id="suggest-modal" class="modal-dialog">
    <form id="suggest-form">
        <h3>Предложить изменения</h3>

        <!-- Семантический алерт -->
        <div role="alert" class="alert is-notice">
            <strong>Внимание:</strong> Ваши изменения будут предложены сообществу.
            Для применения необходимо, чтобы <?= \App\Modules\Suggestions\Services\SuggestionService::QUORUM_SIZE ?>
            пользователей предложили абсолютно одинаковые изменения.
        </div>

        <input type="hidden" name="target_type" id="suggest-target-type">
        <input type="hidden" name="target_id" id="suggest-target-id">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="suggest-title"><strong>Заголовок</strong></label>
            <input type="text"
                id="suggest-title"
                name="title"
                placeholder="Оставьте пустым, если не меняете">
        </div>

        <div class="form-field-group" id="suggest-tags-group">
            <label><strong>Теги</strong></label>
            <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>

            <div class="tags-container">
                <?php foreach ($allTags as $tagItem): ?>
                    <?php $isBound = in_array((int)$tagItem['id'], $currentTagIds ?? []); ?>
                    <!-- Label делает весь блок кликабельным -->
                    <label class="tag-checkbox">
                        <input type="checkbox"
                            name="tags[]"
                            value="<?= (int)$tagItem['id'] ?>"
                            <?= $isBound ? 'checked' : '' ?>>
                        <span><?= e($tagItem['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-field-group hidden" id="suggest-text-group">
            <label for="suggest-text"><strong>Текст</strong></label>
            <textarea id="suggest-text" name="text" rows="5"></textarea>
        </div>

        <div class="form-actions">
            <button type="submit">Отправить предложение</button>
            <!-- Кнопка отмены -->
            <button type="button" class="close-modal-btn is-link">Отмена</button>
        </div>
    </form>
</dialog>