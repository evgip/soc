<h1>Установить новый пароль</h1>

<p class="hint">Придумайте новый надёжный пароль для вашего аккаунта.</p>

<form action="<?= route('password.reset.submit') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="form-field-group">
        <label for="reset-password"><strong>Новый пароль</strong></label>
        <input
            type="password"
            id="reset-password"
            name="password"
            minlength="6"
            required
            autofocus
            class="form-input-wide"
            placeholder="Минимум 6 символов">
    </div>

    <div class="form-field-group">
        <label for="reset-password-confirm"><strong>Подтвердите пароль</strong></label>
        <input
            type="password"
            id="reset-password-confirm"
            name="password_confirm"
            minlength="6"
            required
            class="form-input-wide"
            placeholder="Повторите пароль">
    </div>

    <div class="form-actions">
        <button type="submit">Сменить пароль</button>
    </div>
</form>

<hr>

<p>
    <a href="<?= route('auth.login') ?>">← Вернуться ко входу</a>
</p>