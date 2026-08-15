<h1>Настройки аккаунта</h1>

<p class="hint">
    Вы можете изменить личные контактные данные, рассказать о себе и загрузить графический аватар.
    <strong>150×150px</strong> — идеальный размер.
</p>

<form action="<?= route('account.settings.submit') ?>" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label><strong>Ваш аватар</strong></label>
        <div class="avatar-upload-container">
            <?php if (!empty($user['avatar'])): ?>
                <img src="/uploads/avatars/<?= substr($user['avatar'], 0, 2) ?>/<?= e($user['avatar']) ?>" 
                     class="avatar-preview" alt="Avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder-large">
                    <?= e(mb_substr($user['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <div>
                <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/gif" class="form-input-file <?= $errors->hasError('avatar_file') ? 'is-danger' : '' ?>">
                <?php if ($errors->hasError('avatar_file')): ?>
                    <small class="form-error-text"><?= $errors->firstError('avatar_file') ?></small>
                <?php else: ?>
                    <p class="hint">Рекомендуется квадратное изображение JPG, PNG или GIF. Максимум 5 МБ.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="form-field-group">
        <label for="username"><strong>Имя пользователя</strong></label>
        <input type="text" id="username" class="form-input-wide" value="<?= e($user['username']) ?>" disabled>
        <p class="hint">Имя пользователя является уникальным идентификатором и не может быть изменено самостоятельно.</p>
    </div>

    <div class="form-field-group">
        <label for="email"><strong>Email адрес</strong></label>
        <input type="email" id="email" name="email" 
               class="form-input-wide <?= $errors->hasError('email') ? 'is-danger' : '' ?>" 
               value="<?= e($errors->getOld('email', $user['email'])) ?>" required>
        <?php if ($errors->hasError('email')): ?>
            <small class="form-error-text"><?= $errors->firstError('email') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="bio"><strong>О себе</strong></label>
        <textarea id="bio" name="bio" rows="4" 
                  class="<?= $errors->hasError('bio') ? 'is-danger' : '' ?>"
                  placeholder="Расскажите немного о себе, ваших интересах или проектах..."><?= e($errors->getOld('bio', $user['bio'] ?? '')) ?></textarea>
        <?php if ($errors->hasError('bio')): ?>
            <small class="form-error-text"><?= $errors->firstError('bio') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit">💾 Сохранить изменения</button>
    </div>
</form>

<hr>

<h2>Настройки уведомлений</h2>
<p class="hint">Выберите, о каких событиях вы хотите получать уведомления.</p>

<form action="<?= route('account.settings.submit') ?>" method="POST">
    <?= csrf_field() ?>
    
    <input type="hidden" name="email" value="<?= e($errors->getOld('email', $user['email'])) ?>">
    <input type="hidden" name="bio" value="<?= e($errors->getOld('bio', $user['bio'] ?? '')) ?>">
    
    <div class="notification-settings">
        
        <!-- Уведомления об ответах -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <!-- 🔥 ИСПРАВЛЕНО: Проверка old_input, иначе fallback на настройки из БД -->
                <?php $val = $errors->getOld('notify_on_reply', $settings['notify_on_reply'] ?? 0); ?>
                <input type="checkbox" name="notify_on_reply" value="1" <?= $val ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления об ответах на мои комментарии</strong>
            </label>
            <p class="hint">Получать уведомления, когда кто-то отвечает на ваш комментарий</p>
        </div>
        
        <!-- Уведомления о комментариях к подписанным историям -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <?php $val = $errors->getOld('notify_on_story_comment', $settings['notify_on_story_comment'] ?? 0); ?>
                <input type="checkbox" name="notify_on_story_comment" value="1" <?= $val ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления о комментариях к моим историям</strong>
            </label>
            <p class="hint">Получать уведомления о новых комментариях к историям, на которые вы подписаны</p>
        </div>

        <!-- @-уведомления -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <?php $val = $errors->getOld('notify_on_mention', $settings['notify_on_mention'] ?? 0); ?>
                <input type="checkbox" name="notify_on_mention" value="1" <?= $val ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления при обращении к вам @</strong>
            </label>
            <p class="hint">Получать уведомления, когда кто-то обращается к вам @</p>
        </div>
		
        <!-- Уведомления о личных сообщениях -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <?php $val = $errors->getOld('notify_on_message', $settings['notify_on_message'] ?? 0); ?>
                <input type="checkbox" name="notify_on_message" value="1" <?= $val ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления о личных сообщениях</strong>
            </label>
            <p class="hint">Получать уведомления, когда кто-то отправляет вам личное сообщение</p>
        </div>
        
        <!-- Email-уведомления -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <?php $val = $errors->getOld('email_notifications', $settings['email_notifications'] ?? 0); ?>
                <input type="checkbox" name="email_notifications" value="1" <?= $val ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Дублировать уведомления на email</strong>
            </label>
            <p class="hint">Отправлять копии уведомлений на ваш email (<?= e($errors->getOld('email', $user['email'])) ?>)</p>
        </div>
        
		<!-- Уведомления о новых частях в подписанных сериях -->
		<div class="form-field-group">
			<label class="checkbox-label">
				<?php $val = $errors->getOld('notify_on_collection_update', $settings['notify_on_collection_update'] ?? 1); ?>
				<input type="checkbox" name="notify_on_collection_update" value="1" <?= $val ? 'checked' : '' ?>>
				<span class="checkmark"></span>
				<strong>Уведомления о новых частях в сериях</strong>
			</label>
			<p class="hint">Получать уведомления, когда авторы добавляют новые части в серии, на которые вы подписаны</p>
		</div>
		
		
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Сохранить настройки уведомлений</button>
    </div>
</form>

<hr>

<h2>Изменение пароля</h2>
<p class="hint">
    Для повышения безопасности вашего профиля рекомендуется использовать сложный пароль из букв, цифр и спецсимволов.
    <br>
    <a href="/password/recovery" class="form-field-hint-inline">Не помните текущий пароль?</a>
</p>

<form action="<?= route('account.password.submit') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="current_password"><strong>Текущий пароль</strong></label>
        <input type="password" id="current_password" name="current_password" 
               class="form-input-wide <?= $errors->hasError('current_password') ? 'is-danger' : '' ?>" 
               required placeholder="Введите ваш действующий пароль">
        <?php if ($errors->hasError('current_password')): ?>
            <small class="form-error-text"><?= $errors->firstError('current_password') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="new_password"><strong>Новый пароль</strong></label>
        <!-- Обратите внимание: для паролей мы НЕ используем getOld из соображений безопасности -->
        <input type="password" id="new_password" name="new_password" 
               class="form-input-wide <?= $errors->hasError('new_password') ? 'is-danger' : '' ?>" 
               required minlength="6" placeholder="Минимум 6 символов">
        <?php if ($errors->hasError('new_password')): ?>
            <small class="form-error-text"><?= $errors->firstError('new_password') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="confirm_password"><strong>Подтвердите новый пароль</strong></label>
        <input type="password" id="confirm_password" name="password_confirm" 
               class="form-input-wide <?= $errors->hasError('password_confirm') ? 'is-danger' : '' ?>" 
               required minlength="6" placeholder="Повторите новый пароль">
        <?php if ($errors->hasError('password_confirm')): ?>
            <small class="form-error-text"><?= $errors->firstError('password_confirm') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit">🔑 Обновить пароль</button>
    </div>
</form>