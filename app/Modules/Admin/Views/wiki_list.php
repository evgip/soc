<?php
/**
 * Список всех wiki страниц в админке
 * 
 * @var array $pages Массив wiki страниц
 * @var int $currentPage Текущая страница пагинации
 * @var int $perPage Количество на страницу
 * @var int $totalPages Всего страниц
 * @var int $deletedPages Количество удалённых
 * @var int $totalPagesCount Количество страниц пагинации
 */
?>

<h1><?= e($title ?? 'Управление Wiki страницами') ?></h1>

<div class="hint" style="margin-bottom: 1em;">
    Wiki страницы привязаны к тегам и содержат документацию, FAQ, правила и другие справочные материалы.
    <br>
    Всего страниц: <strong><?= (int)$totalPages ?></strong>, 
    удалённых: <strong style="color: #ac130d;"><?= (int)$deletedPages ?></strong>.
    <br>
    <small>
        Удаление — мягкое (soft delete), страницы можно восстановить. 
        Полное удаление из базы данных не производится.
    </small>
</div>

<?php if (empty($pages)): ?>
    <div class="hint" style="text-align: center; padding: 2em 0;">
        <p>Wiki страницы пока не созданы.</p>
        <p>Создайте первую страницу через любой тег: <code>/t/{tag}/wiki/create</code></p>
    </div>
<?php else: ?>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 40px;">ID</th>
                <th>Заголовок / URL</th>
                <th>Тег</th>
                <th>Автор</th>
                <th>Статус</th>
                <th style="width: 60px;">Основная</th>
                <th style="width: 80px;">Просмотры</th>
                <th style="width: 120px;">Дата</th>
                <th style="width: 220px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): ?>
                <?php 
                $isDeleted = !empty($page['deleted_at']);
                $rowStyle = $isDeleted 
                    ? 'style="background-color: #fff5f5; color: #ac130d;"' 
                    : '';
                ?>
                <tr <?= $rowStyle ?>>
                    <td><?= (int)$page['id'] ?></td>
                    <td>
                        <strong <?= $isDeleted ? 'style="text-decoration: line-through;"' : '' ?>>
                            <?= e($page['title']) ?>
                        </strong>
                        <?php if ($isDeleted): ?>
                            <span class="tag red">Удалена</span>
                        <?php endif; ?>
                        <br>
                        <small class="hint">
                            <code>/t/<?= e($page['tag_slug'] ?? '') ?>/wiki/<?= e($page['slug']) ?></code>
                        </small>
                    </td>
                    <td>
                        <?php if (!empty($page['tag_name'])): ?>
                            <a href="/t/<?= e($page['tag_slug']) ?>" target="_blank">
                                <span class="tag tag-<?= e($page['tag_slug']) ?>">
                                    #<?= e($page['tag_name']) ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <span class="hint">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($page['author_name'])): ?>
                            <a href="/user/<?= e($page['author_name']) ?>" target="_blank">
                                <?= e($page['author_name']) ?>
                            </a>
                        <?php else: ?>
                            <span class="hint">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusLabels = [
                            'published' => '<span class="text-success">✓ Опубликована</span>',
                            'draft'     => '<span class="text-danger">✎ Черновик</span>',
                            'archived'  => '<span class="text-muted">📦 В архиве</span>',
                        ];
                        echo $statusLabels[$page['status']] ?? e($page['status']);
                        ?>
                    </td>
                    <td>
                        <?= !empty($page['is_primary']) ? '<strong class="text-success">★ Да</strong>' : 'Нет' ?>
                    </td>
                    <td><?= (int)$page['view_count'] ?></td>
                    <td>
                        <span title="<?= dt($page['created_at'], 'd.m.Y H:i:s') ?>">
                            Создана: <?= dt($page['created_at']) ?>
                        </span>
                        <?php if ($isDeleted): ?>
                            <br>
                            <small style="color: #ac130d;">
                                Удалена: <?= dt($page['deleted_at']) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isDeleted): ?>
                            <a href="/t/<?= e($page['tag_slug']) ?>/wiki/<?= e($page['slug']) ?>" 
                               class="button" 
                               target="_blank"
                               title="Просмотр на сайте">
                                👁️
                            </a>
                            <a href="/t/<?= e($page['tag_slug']) ?>/wiki/<?= (int)$page['id'] ?>/edit" 
                               class="button"
                               title="Редактировать">
                                ✏️
                            </a>
                            <form method="POST"
                                  action="<?= route('admin.wiki.delete', ['id' => $page['id']]) ?>"
                                  style="display:inline;"
                                  class="delete-link"
                                  data-confirm="Удалить wiki страницу «<?= e($page['title']) ?>»? Её можно будет восстановить.">
                                <?= csrf_field() ?>
                                <button type="submit" class="button" style="color: #ac130d;" title="Удалить (soft delete)">
                                    🗑️
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST"
                                  action="<?= route('admin.wiki.restore', ['id' => $page['id']]) ?>"
                                  style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="button" title="Восстановить">
                                    ♻️ Восстановить
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($totalPagesCount > 1): ?>
        <div class="pagination" style="margin-top: 1em;">
            <?php if ($currentPage > 1): ?>
                <a href="/admin/wiki?page=<?= $currentPage - 1 ?>">← Назад</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPagesCount; $i++): ?>
                <?php if ($i === $currentPage): ?>
                    <strong><?= $i ?></strong>
                <?php else: ?>
                    <a href="/admin/wiki?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPagesCount): ?>
                <a href="/admin/wiki?page=<?= $currentPage + 1 ?>">Вперёд →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>