<?php /** @var array $invitations */ ?>
<?php /** @var int $activeCount */ ?>
<?php /** @var int $maxInvitations */ ?>
<?php /** @var bool $hasEnoughKarma */ ?>
<?php /** @var int $minKarma */ ?>

<h1>🎟️ Управление приглашениями</h1>

<?php if (!$hasEnoughKarma): ?>
    <div role="alert" class="alert is-danger">
        <strong>⚠️ Недостаточно кармы!</strong><br>
        Для создания приглашений необходимо минимум <strong><?= (int)$minKarma ?></strong> кармы.
    </div>
<?php endif; ?>

<div class="form-field-group">
    <h2>Создать новое приглашение</h2>
    <p class="hint">
        Активных приглашений: <strong><?= (int)$activeCount ?></strong> из
        <strong><?= (int)$maxInvitations ?></strong>
        (осталось <?= (int)($maxInvitations - $activeCount) ?>)
    </p>

    <?php if ($hasEnoughKarma && $activeCount < $maxInvitations): ?>
        <form method="POST" action="<?= route('invitations.create') ?>">
            <?= csrf_field() ?>

            <div class="form-field-group">
                <label for="email">
                    <strong>Email получателя</strong>
                    <span class="form-field-hint-inline">— необязательно</span>
                </label>
                <input type="email" id="email" name="email"
                       placeholder="Оставьте пустым, чтобы получить ссылку для себя"
                       class="form-input-wide">
                <small class="form-text text-muted hint">
                    Если укажете email, приглашение будет отправлено автоматически.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit">Создать приглашение</button>
            </div>
        </form>
    <?php else: ?>
        <div class="flash-notice">
            Вы достигли лимита активных приглашений или недостаточно кармы.
        </div>
    <?php endif; ?>
</div>

<div class="form-field-group">
    <h2>Ваши приглашения</h2>

    <?php if (empty($invitations)): ?>
        <p class="hint">У вас пока нет приглашений.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>Код</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Использовал</th>
                    <th>Создано</th>
                    <th>Истекает</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invitations as $inv): ?>
                    <?php
                        $statusClass = match($inv['status']) {
                            'pending'  => 'status-pending',
                            'accepted' => 'status-accepted',
                            'expired'  => 'status-expired',
                            'revoked'  => 'status-revoked',
                            default    => '',
                        };
                        $statusText = match($inv['status']) {
                            'pending'  => 'Ожидает',
                            'accepted' => 'Принято',
                            'expired'  => 'Истекло',
                            'revoked'  => 'Отозвано',
                            default    => e($inv['status']),
                        };
                        $inviteUrl = route('home') . 'register/invite/' . $inv['code'];
                        $isActive = $inv['status'] === 'pending' && strtotime($inv['expires_at']) > time();
                    ?>
                    <tr>
                        <td>
                            <code><?= e($inv['code']) ?></code>
                        </td>
                        <td>
                            <?php if (!empty($inv['invitee_email'])): ?>
                                <?= e($inv['invitee_email']) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= $statusText ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($inv['invitee_username'])): ?>
                                <a href="/u/<?= e($inv['invitee_username']) ?>">
                                    @<?= e($inv['invitee_username']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= dt($inv['created_at']) ?></td>
                        <td><?= dt($inv['expires_at']) ?></td>
                        <td class="invitation-actions">
                            <?php if ($isActive): ?>
                                <button type="button"
                                        class="btn-secondary btn-copy"
                                        data-url="<?= e($inviteUrl) ?>"
                                        title="Скопировать ссылку">
                                      Копировать
                                </button>
                                <form method="POST"
                                      action="<?= route('invitations.revoke', ['id' => $inv['id']]) ?>"
                                      class="inline-form restore-link"
                                      data-confirm="Отозвать это приглашение?">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn-danger"
                                            title="Отозвать">
                                        ✖ Отозвать
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script nonce="<?= csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.dataset.url;
            const originalText = this.innerHTML;

            navigator.clipboard.writeText(url).then(() => {
                this.innerHTML = '✅ Скопировано';
                setTimeout(() => { this.innerHTML = originalText; }, 1500);
            }).catch(() => {
                // Fallback для старых браузеров
                const textarea = document.createElement('textarea');
                textarea.value = url;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    this.innerHTML = '✅ Скопировано';
                    setTimeout(() => { this.innerHTML = originalText; }, 1500);
                } catch (err) {
                    alert('Ссылка для копирования: ' + url);
                }
                document.body.removeChild(textarea);
            });
        });
    });
});
</script>

