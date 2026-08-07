<h1>📋 Лог модерации</h1>

<p class="text-muted">
    Всего записей: <strong><?= (int)$total ?></strong>
</p>

<?php if (empty($items)): ?>
    <p class="text-muted">Записей пока нет.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Модератор</th>
                <th>IP</th>
                <th>Действие</th>
                <th>Описание</th>
                <th>Детали</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e(date('d.m.Y H:i', strtotime($item['created_at']))) ?></td>
                    <td>
                        <strong><?= e($item['username']) ?></strong>
                        <br>
                        <small class="text-muted">
                            <span class="badge bg-secondary"><?= e($item['role']) ?></span>
                        </small>
                    </td>
                    <td><code><?= e($item['ip_address']) ?></code></td>
                    <td><code><?= e($item['action']) ?></code></td>
                    <td><?= e($item['description']) ?></td>
                    <td>
                        <?php if (!empty($item['decoded_payload'])): ?>
                            <details>
                                <summary class="text-primary">Показать</summary>
                                <pre><?= e(json_encode($item['decoded_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </details>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <nav class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="/mod/log?page=<?= $i ?>" 
                   class="<?= $i === $current_page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>