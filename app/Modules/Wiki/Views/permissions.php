<div class="wiki-permissions">
    <!-- Хлебные крошки -->
    <nav class="breadcrumbs">
        <a href="/">Главная</a> →
        <a href="/t/<?= e($tag['slug']) ?>">#<?= e($tag['name']) ?></a> →
        <a href="<?= route('wiki.tag.index', ['slug' => $tag['slug']]) ?>">Wiki</a> →
        <span>Управление правами</span>
    </nav>

    <h1>👥 Управление правами wiki для тега #<?= e($tag['name']) ?></h1>

    <section class="current-editors">
        <h2>Текущие редакторы</h2>

        <?php if (empty($editors)): ?>
            <p class="empty">Нет назначенных редакторов</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Права</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($editors as $editor): ?>
                        <tr>
                            <td>
                                <a href="/user/<?= e($editor['username']) ?>">
                                    <?= e($editor['username']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($editor['can_edit']): ?>
                                    <span class="badge">✏️ Редактирование</span>
                                <?php endif; ?>
                                <?php if ($editor['can_delete']): ?>
                                    <span class="badge badge-danger">🗑️ Удаление</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="<?= route('wiki.tag.permissions.revoke', ['slug' => $tag['slug']]) ?>"
                                    method="POST"
                                    data-confirm="Отозвать права?">
                                    <input type="hidden" name="user_id" value="<?= $editor['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Отозвать</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="add-editor">
        <h2>Добавить редактора</h2>

        <form action="<?= route('wiki.tag.permissions.grant', ['slug' => $tag['slug']]) ?>"
            method="POST"
            class="permission-form">
            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <input type="text"
                    id="username"
                    name="username"
                    required
                    placeholder="Введите имя пользователя">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="can_edit" value="1" checked>
                    Может редактировать wiki страницы
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="can_delete" value="1">
                    Может удалять wiki страницы
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Выдать права</button>
        </form>
    </section>
</div>