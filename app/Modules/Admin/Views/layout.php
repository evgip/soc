<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Админ-панель | <?= e($title ?? '') ?></title>

    <link rel="stylesheet" href="/css/app.min.css">
    <link rel="stylesheet" href="/css/admin.min.css">
    <script src="<?= \W3a\Core\Asset::js() ?>"></script>

</head>

<body>

    <aside class="sidebar">
        <h3><?= __('admin_panel') ?></h3>
        <nav>
            <a href="/admin">📊 Главная панель</a>
            <a href="/admin/users">👥 Пользователи</a>
            <a href="/admin/tags">🏷️ Теги</a>
            <a href="/admin/categories">📂 Категории</a>
			 <a href="/admin/wiki">📖 Wiki</a>
            <a href="/admin/invitations">📨 Инвайты</a>
            <a href="/admin/audit">🔒 Журнал аудита</a>
            <a href="/admin/firewall">🧱 Firewall</a>
            <a href="/admin/tools">🛠️ Инструменты</a>
            <a href="/" target="_blank">🌐 Перейти на сайт</a>
        </nav>
    </aside>

    <div class="main-content">
        <header class="navbar">
            <div class="page-title"><strong><?= e($title ?? '') ?></strong></div>
            <div class="user-meta">
                Добро пожаловать, <b><?= e($_SESSION['user_name'] ?? 'Администратор') ?></b> |
                <a href="/logout" class="gray">Выйти</a>
            </div>
        </header>

        <main class="container">
            <?= render_flashes() ?>
            <?= $content ?>
        </main>

        <?= \W3a\Core\Benchmark::renderStats() ?>
    </div>

</body>

</html>