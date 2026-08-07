<?php /** @var string $code */ ?>
<?php /** @var array $invitation */ ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">🎟️ Регистрация по приглашению</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Отлично!</strong> Вас пригласили в закрытое сообщество.
                        Заполните форму ниже, чтобы завершить регистрацию.
                    </div>

                    <p class="text-muted small">
                        Приглашение действительно до <strong><?= dt($invitation['expires_at']) ?></strong>
                    </p>

                    <form method="POST" action="<?= route('home') ?>register/invite/<?= e($code) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="invite_code" value="<?= e($code) ?>">

                        <div class="mb-3">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   required
                                   minlength="3"
                                   maxlength="50"
                                   pattern="[a-zA-Z0-9_]+"
                                   placeholder="Только латиница, цифры и _">
                            <small class="text-muted">От 3 до 50 символов</small>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   required
                                   value="<?= e($invitation['invitee_email'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   required
                                   minlength="6">
                            <small class="text-muted">Минимум 6 символов</small>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Подтверждение пароля</label>
                            <input type="password"
                                   class="form-control"
                                   id="password_confirm"
                                   name="password_confirm"
                                   required
                                   minlength="6">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                Зарегистрироваться
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted small">
                    Уже есть аккаунт? <a href="<?= route('auth.login') ?>">Войти</a>
                </div>
            </div>
        </div>
    </div>
</div>