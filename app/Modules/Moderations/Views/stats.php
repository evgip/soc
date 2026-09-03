<?php partial('Moderations::_mod_nav') ?>
<h1>📈 Активность модераторов</h1>
<p class="text-muted">Статистика за последние 30 дней</p>

<!-- Сводная таблица -->
<h2>🏆 Рейтинг</h2>
<?php if (empty($leaderboard)): ?>
    <p class="text-muted">Данных нет.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Модератор</th>
                <th style="text-align: right;">Действий</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaderboard as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['moderator_name'] ?? '—') ?></td>
                    <td style="text-align: right;"><strong><?= (int)$row['total_actions'] ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<hr>

<!-- Детальная статистика -->
<h2>📅 По дням и действиям</h2>
<?php if (empty($stats)): ?>
    <p class="text-muted">Данных нет.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Модератор</th>
                <th>Действие</th>
                <th style="text-align: right;">Кол-во</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats as $row): ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                    <td><?= htmlspecialchars($row['moderator_name'] ?? '—') ?></td>
                    <td><code><?= htmlspecialchars($row['action']) ?></code></td>
                    <td style="text-align: right;"><?= (int)$row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>