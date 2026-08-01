<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Админ-панель | <?= e($title ?? '') ?></title>

    <link rel="stylesheet" href="/css/app.min.css">
    <link rel="stylesheet" href="/css/admin.min.css">
    <script src="<?= \W3a\Core\View\Asset::js() ?>"></script>

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
				
							<form class="dropdown-menu-item" action="<?= route('auth.logout') ?>" method="POST">
								<?= csrf_field() ?>
								<button type="submit" class="is-link bold">🚪 <?= __('logout') ?></button>
							</form>
            </div>
        </header>

        <main class="container">

			<?php if (!empty($errors->allErrors())): ?>
				<!-- 1. Блок ошибок валидации -->
				<div class="alert is-danger">
					<strong>Ошибка валидации!</strong>
					<ul class="validation-errors-list">
						<?php foreach ($errors->allErrors() as $fieldErrors): ?>
							<?php foreach ($fieldErrors as $err): ?>
								<li><?= htmlspecialchars($err) ?></li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- 2. Блок обычных flash-сообщений -->
			<?php if ($flashError = $errors->getFlash('error')): ?>
				<div class="alert is-danger"><strong>Ошибка!</strong> <?= htmlspecialchars($flashError) ?></div>
			<?php endif; ?>

			<?php if ($flashSuccess = $errors->getFlash('success')): ?>
				<div class="alert is-success"><strong>Успех!</strong> <?= htmlspecialchars($flashSuccess) ?></div>
			<?php endif; ?>

			<?php if ($flashNotice = $errors->getFlash('notice')): ?>
				<div class="alert is-notice"><strong>Информация!</strong> <?= htmlspecialchars($flashNotice) ?></div>
			<?php endif; ?>


            <?= $content ?>
        </main>

        <?= \W3a\Core\Support\Benchmark::renderStats() ?>
    </div>

</body>

</html>