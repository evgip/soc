<?php

/** @var array $requests */ ?>
<?php /** @var string $currentStatus */ ?>

<div class="container mt-4">
    <h1>📨 Запросы приглашений</h1>

    <nav>

        <a class="nav-link <?= $currentStatus === 'pending' ? 'active' : '' ?>"
            href="?status=pending">
            Ожидают (<?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')) ?>)
        </a>

        <a class="nav-link <?= $currentStatus === 'approved' ? 'active' : '' ?>"
            href="?status=approved">
            Одобренные
        </a>

        <a class="nav-link <?= $currentStatus === 'rejected' ? 'active' : '' ?>"
            href="?status=rejected">
            Отклонённые
        </a>

    </nav>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">Нет запросов в этой категории.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Причина</th>
                        <th>IP</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= e($req['email']) ?></td>
                            <td style="max-width: 400px;"><?= nl2br(e($req['reason'])) ?></td>
                            <td><code><?= e($req['ip_address']) ?></code></td>
                            <td><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= route('admin.invitations.approve', ['id' => $req['id']]) ?>"
                                        style="display: inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-success">✓ Одобрить</button>
                                    </form>
                                    <form method="POST" action="<?= route('admin.invitations.reject', ['id' => $req['id']]) ?>"
                                        style="display: inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger">✖ Отклонить</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-<?= $req['status'] === 'approved' ? 'success' : 'secondary' ?>">
                                        <?= $req['status'] === 'approved' ? 'Одобрено' : 'Отклонено' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>