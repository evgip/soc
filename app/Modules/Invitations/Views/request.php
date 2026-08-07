<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">📨 Запрос приглашения</h4>
                </div>
                <div class="card-body">

                    <p>
                        Наше сообщество работает по системе приглашений, но вы можете отправить запрос.
                        Модераторы рассмотрят вашу заявку и, если она будет одобрена, вышлют приглашение на email.
                    </p>

                    <div class="alert alert-info">
                        <strong>💡 Совет:</strong> Расскажите, чем вы можете быть полезны сообществу.
                        Чем подробнее вы опишете свои интересы и опыт, тем выше шансы на одобрение.
                    </div>

                    <form method="POST" action="<?= route('home') ?>invite/request">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">Ваш email *</label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   required
                                   placeholder="your@email.com">
                            <small class="text-muted">На этот адрес будет отправлено приглашение</small>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Почему вы хотите присоединиться? *</label>
                            <textarea class="form-control"
                                      id="reason"
                                      name="reason"
                                      rows="6"
                                      required
                                      minlength="10"
                                      placeholder="Расскажите о себе, своих интересах и почему хотите стать частью сообщества..."></textarea>
                            <small class="text-muted">Минимум 10 символов</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Отправить запрос
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted small">
                    Есть приглашение? <a href="<?= route('home') ?>">Перейти на главную</a>
                </div>
            </div>
        </div>
    </div>
</div>