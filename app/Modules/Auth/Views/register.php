<h1>Регистрация</h1>

<p class="hint">Создайте аккаунт, чтобы присоединиться к сообществу.</p>

<form action="/register" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="register-username"><strong>Имя пользователя</strong></label>
        <input
            type="text"
            id="register-username"
            name="username"
            required
            autofocus
            minlength="3"
            maxlength="20"
            pattern="[a-zA-Z0-9_]+"
            class="form-input-wide <?= $errors->hasError('username') ? 'is-danger' : '' ?>"
            placeholder="Например: john_doe"
            value="<?= e($errors->getOld('username')) ?>">
        
        <?php if ($errors->hasError('username')): ?>
            <small class="form-error-text"><?= $errors->firstError('username') ?></small>
        <?php else: ?>
            <p class="hint">Только латинские буквы, цифры и символ подчёркивания (3-20 символов).</p>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="register-email"><strong>Email</strong></label>
        <input
            type="email"
            id="register-email"
            name="email"
            required
            class="form-input-wide <?= $errors->hasError('email') ? 'is-danger' : '' ?>"
            placeholder="name@example.com"
            value="<?= e($errors->getOld('email')) ?>">
        
        <?php if ($errors->hasError('email')): ?>
            <small class="form-error-text"><?= $errors->firstError('email') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="register-password"><strong>Пароль</strong></label>
        <!-- Для паролей значение НЕ подставляем из соображений безопасности -->
        <input
            type="password"
            id="register-password"
            name="password"
            required
            minlength="6"
            class="form-input-wide <?= $errors->hasError('password') ? 'is-danger' : '' ?>"
            placeholder="Минимум 6 символов">
        
        <?php if ($errors->hasError('password')): ?>
            <small class="form-error-text"><?= $errors->firstError('password') ?></small>
        <?php else: ?>
            <p class="hint">Используйте буквы, цифры и специальные символы для надёжности.</p>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="register-password-confirm"><strong>Подтверждение пароля</strong></label>
        <input
            type="password"
            id="register-password-confirm"
            name="password_confirmation"
            required
            minlength="6"
            class="form-input-wide <?= $errors->hasError('password_confirmation') ? 'is-danger' : '' ?>"
            placeholder="Повторите пароль">
        
        <?php if ($errors->hasError('password_confirmation')): ?>
            <small class="form-error-text"><?= $errors->firstError('password_confirmation') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <?= captcha() ?>
        <?php if ($errors->hasError('captcha')): ?>
            <small class="form-error-text"><?= $errors->firstError('captcha') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit">Зарегистрироваться</button>
    </div>
</form>

<hr>

<p>
    Уже есть аккаунт? <a href="/login">Войти</a>
</p>


<!--?php partial('SocialAuth::_buttons'); ?-->