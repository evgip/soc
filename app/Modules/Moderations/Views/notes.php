<?php partial('Moderations::_mod_nav') ?>
<h1>🔒 Модераторские заметки</h1>

<!-- Форма добавления заметки -->
<form method="POST" action="/mod/notes/store" class="mod-note-form">
    <?= csrf_field() ?>
    
  <div class="form-group">
        <label for="mod_user_id">ID пользователя:</label>
        <input type="number" 
               id="mod_user_id"
               name="user_id" 
               required 
               min="1" 
               class="form-control"
               value="<?= isset($target_user_id) && $target_user_id > 0 ? htmlspecialchars((string)$target_user_id) : '' ?>"
               <?= isset($target_user_id) && $target_user_id > 0 ? 'autofocus' : '' ?>>
        
        <!-- Опционально: подсказка, если ID подставлен -->
        <?php if (isset($target_user_id) && $target_user_id > 0): ?>
            <small class="text-muted">ID подставлен автоматически из ссылки.</small>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label>Заметка:</label>
        <textarea name="note" required rows="3" class="form-control"></textarea>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="is_private" value="1" checked>
            Приватная (видна только модераторам)
        </label>
    </div>
    
    <button type="submit" class="btn btn-primary">Добавить заметку</button>
</form>

<hr>

<!-- Список заметок -->
<?php if (empty($notes)): ?>
    <p class="text-muted">Заметок пока нет.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Пользователь</th>
                <th>Модератор</th>
                <th>Заметка</th>
                <th>Видимость</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notes as $note): ?>
            <tr class="<?= $note['is_private'] ? 'table-warning' : '' ?>">
                <td><?= htmlspecialchars($note['created_at']) ?></td>
                <td><?= htmlspecialchars($note['target_username'] ?? "User #{$note['user_id']}") ?></td>
                <td><?= htmlspecialchars($note['moderator_name'] ?? '—') ?></td>
                <td><?= nl2br(htmlspecialchars($note['note'])) ?></td>
                <td><?= $note['is_private'] ? '🔒 Приватная' : '🌐 Публичная' ?></td>
                <td>
                    <form method="POST" action="/mod/notes/<?= $note['id'] ?>/delete">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-danger flag-link" data-confirm="Удалить заметку?">✕</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>