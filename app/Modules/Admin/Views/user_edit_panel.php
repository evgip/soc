<?php

/** 
 * @var array $userItem 
 * @var W3a\Core\Http\Request $request 
 */
?>

<h1>🔧 Панель модерации: <?= e($userItem['username'] ?? 'Пользователь') ?></h1>

<p class="hint">
    Управление профилем, правами доступа и медиа-контентом пользователя.
</p>

<form action="<?= route('admin.users.edit.submit', ['id' => $userItem['id'] ?? 0]) ?>" method="POST">
    <?= csrf_field() ?>

    <!-- Секция аватара -->
    <div class="form-field-group">
        <label>Аватар пользователя</label>

        <?php if (!empty($userItem['avatar'])): ?>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                <img src="/uploads/avatars/<?= substr($userItem['avatar'], 0, 2) ?>/<?= e($userItem['avatar']) ?>"
                    style="width: 64px; height: 64px; border-radius: 4px; object-fit: cover; background: #f0f0f0;"
                    alt="Аватар">
                <div>
                    <strong>Пользовательский аватар установлен</strong><br>
                    <span class="hint">Файл: <?= e($userItem['avatar']) ?></span>
                </div>
            </div>

            <form action="<?= route('admin.users.avatar.delete', ['id' => $userItem['id']]) ?>" method="POST" style="display:inline; margin-top: 0.5rem;">
                <?= csrf_field() ?>
                <button type="submit" class="button delete-link" style="color: #ac130d;" data-confirm="Вы уверены, что хотите удалить аватар этого пользователя?">
                    🗑️ Удалить аватар
                </button>
            </form>
        <?php else: ?>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                <div style="width: 64px; height: 64px; background: #e0e0e0; color: #666; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; border-radius: 4px;">
                    <?= e(mb_substr($userItem['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <strong>Стандартный аватар-заглушка</strong><br>
                    <span class="hint">Пользователь еще не загрузил личную фотографию.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Основные поля редактирования -->
    <div class="form-field-group">
        <label for="email">Email адрес <span class="form-field-hint-inline">(обязательно)</span></label>
        <input type="email" id="email" name="email" required class="form-input-wide"
            value="<?= e($request->getParams('email', $userItem['email'] ?? '')) ?>">
        <div class="hint">Основной адрес электронной почты для связи и уведомлений.</div>
    </div>

    <div class="form-field-group">
        <label for="role">Уровень прав доступа (Роль) <span class="form-field-hint-inline">(обязательно)</span></label>
        <select id="role" name="role" required class="form-input-wide">
            <option value="user" <?= ($request->getParams('role', $userItem['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>
                user (Обычный пользователь)
            </option>
            <option value="moderator" <?= ($request->getParams('role', $userItem['role'] ?? 'user') === 'moderator') ? 'selected' : '' ?>>
                moderator (Модератор системы)
            </option>
            <option value="admin" <?= ($request->getParams('role', $userItem['role'] ?? 'user') === 'admin') ? 'selected' : '' ?>>
                admin (Администратор системы)
            </option>
        </select>
        <div class="hint">Определяет доступные функции в панели управления.</div>
    </div>

    <div class="form-field-group">
        <label for="bio">Биография пользователя (О себе)</label>
        <textarea id="bio" name="bio" rows="4" class="form-input-wide"
            placeholder="Краткая информация о пользователе..."><?= e($request->getParams('bio', $userItem['bio'] ?? '')) ?></textarea>
        <div class="hint">Отображается в публичном профиле пользователя (необязательно).</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">💾 Сохранить изменения</button>
        <a href="<?= route('admin.users') ?>" class="button">Отмена</a>
    </div>
</form>