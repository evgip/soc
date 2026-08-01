<h1>Вход в систему</h1>

<p class="hint">Пожалуйста, укажите ваши данные для авторизации.</p>

<form action="/login" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="login-email"><strong>Email</strong></label>
        <!-- Используем $errors->getOld() вместо глобального old() -->
        <!-- Используем $errors->hasError() вместо has_error() -->
        <input type="email" 
               id="login-email" 
               name="email" 
               value="<?= e($errors->getOld('email')) ?>" 
               required 
               autofocus 
               class="form-input-wide <?= $errors->hasError('email') ? 'is-danger' : '' ?>" 
               placeholder="name@example.com">
        
        <!-- Используем $errors->firstError() вместо error_for() -->
        <?php if ($errors->hasError('email')): ?>
            <small class="form-error-text"><?= $errors->firstError('email') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label for="login-password">
            <strong>Пароль</strong>
            <a href="/password/reset" class="form-field-hint-inline">(забыли пароль?)</a>
        </label>
        <!-- Для пароля значение НЕ подставляем из соображений безопасности -->
        <input type="password" 
               id="login-password" 
               name="password" 
               required 
               class="form-input-wide <?= $errors->hasError('password') ? 'is-danger' : '' ?>">
        
        <?php if ($errors->hasError('password')): ?>
            <small class="form-error-text"><?= $errors->firstError('password') ?></small>
        <?php endif; ?>
    </div>

    <div class="form-field-group">
        <label>
            <!-- Сохраняем состояние чекбокса через $errors->getOld() -->
            <input type="checkbox" name="remember" value="1" <?= $errors->getOld('remember') ? 'checked' : '' ?>>
            Запомнить меня на этом компьютере
        </label>
    </div>

    <div class="form-actions">
        <button type="submit">Войти</button>
    </div>
</form>

<hr>

<p>
    Нет аккаунта? <a href="/register">Зарегистрироваться</a>
</p>