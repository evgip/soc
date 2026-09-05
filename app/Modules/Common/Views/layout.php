<?php
declare(strict_types=1);

use App\Modules\Common\Support\Layout;

// Читаем layout, установленный контроллером
$layoutClass = Layout::getClass();
$bodyClass = Layout::getBodyClass();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrf-token" content="<?= e($csrf_token ?? '') ?>">
	<title><?= e($title ?? 'Лента историй') ?> | <?= e(app_name()); ?> <?= __('articles') ?></title>
	<?= \W3a\Core\View\OpenGraph::render() ?>

	<?php if (!empty($rssFeed)): ?>
		<link rel="alternate" type="application/rss+xml"
			title="<?= e($rssFeed['title']) ?>"
			href="<?= e($rssFeed['url']) ?>">
	<?php else: ?>
		<link rel="alternate" type="application/rss+xml"
			title="<?= e(app_name()) ?>"
			href="/rss">
	<?php endif; ?>

	<script nonce="<?= csp_nonce(); ?>">
		(function() {
			var theme = localStorage.getItem('w3a_theme');
			if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
				theme = 'dark';
			}
			if (theme === 'dark') {
				document.documentElement.setAttribute('data-theme', 'dark');
			}
		})();
		// Делегированные confirm-диалоги: заменяют инлайн-обработчики onclick/onsubmit,
		// которые запрещены строгим CSP. Используйте data-confirm="..." на форме или кнопке.
		document.addEventListener('submit', function (e) {
			var msg = e.target && e.target.getAttribute ? e.target.getAttribute('data-confirm') : null;
			if (msg && !window.confirm(msg)) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);

		document.addEventListener('click', function (e) {
			var el = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
			if (el) {
				if (!window.confirm(el.getAttribute('data-confirm'))) {
					e.preventDefault();
					e.stopPropagation();
				}
			}
		}, true);
	</script>
    
	<link rel="stylesheet" href="<?= \W3a\Core\View\Asset::css(); ?>">
</head>

<body class="<?= $bodyClass ?>">

	<header class="header">
		<div class="navbar-container">
			<a href="<?= route('home') ?>" class="navbar-logo"><?= e(app_name()); ?></a>

			<nav class="navbar-links">
				<a class="mb-none" href="<?= route('comments.index') ?>"><?= __('comments') ?></a>
				<a href="<?= route('tags.index') ?>"><?= __('tags') ?></a>
				<a class="mb-none" href="<?= route('search.index') ?>"><?= __('search') ?></a>

				<button type="button" id="theme-toggle" class="theme-toggle" title="<?= __('toggle_theme') ?>" aria-label="<?= __('toggle_theme') ?>">
					<svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
					</svg>
					<svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="5"></circle>
						<line x1="12" y1="1" x2="12" y2="3"></line>
						<line x1="12" y1="21" x2="12" y2="23"></line>
						<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
						<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
						<line x1="1" y1="12" x2="3" y2="12"></line>
						<line x1="21" y1="12" x2="23" y2="12"></line>
						<line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
						<line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
					</svg>
				</button>

				<?php if (!empty($currentUser['isLoggedIn'])): ?>
					<a href="/notifications" class="header-notification-link" id="header-notifications-link" aria-label="<?= __('notifications') ?>">

						<svg class="header-notification-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
							<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
						</svg>

						<span id="header-notification-badge" class="header-notification-badge"><?= (int)($unreadNotificationsCount ?? 0) ?></span>
					</a>

					<div class="navbar-user-dropdown-container" id="user-dropdown-wrapper">

						<button class="dropdown-trigger-btn" id="user-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
							<div class="dropdown-avatar-badge-wrapper">
								<?php if (!empty($currentUser['avatar'])): ?>
									<img src="/uploads/avatars/<?= substr($currentUser['avatar'], 0, 2) ?>/<?= e($currentUser['avatar']) ?>" class="mini-avatar-img" alt="avatar">
								<?php else: ?>
									<span class="mini-avatar-placeholder"><?= e(mb_substr($currentUser['name'] ?? '?', 0, 1)) ?></span>
								<?php endif; ?>

								<?php if (($unreadNotificationsCount ?? 0) > 0 || ($pendingFlagsCount ?? 0) > 0): ?>
									<span class="nav-trigger-alert-dot"></span>
								<?php endif; ?>
							</div>
							<span><?= e($currentUser['name'] ?? '') ?></span>
							<span class="dropdown-arrow-icon">▼</span>
						</button>

<div class="dropdown-menu" id="user-dropdown-menu">
							<a href="<?= route('user.profile', ['username' => $currentUser['name']]) ?>" class="dropdown-menu__item"><span>🙍 <?= __('profile') ?></span></a>

							<a href="<?= route('account.settings') ?>" class="dropdown-menu__item"><span>⚙️ <?= __('settings') ?></span></a>

						    <div class="dropdown-menu__divider"></div>
							
							<a href="<?= route('story.create') ?>" class="dropdown-menu__item"><span>➕ <?= __('share') ?></span></a>

							<?php if (!empty($currentUser['isAdmin'])): ?>
								<div class="dropdown-menu__divider"></div>
								<a href="/admin" class="dropdown-menu__item dropdown-menu__item--danger"><span>📊 <?= __('admin_panel') ?></span></a>
							<?php endif; ?>

							<?php if (!empty($currentUser['isModerator'])): ?>
								<div class="dropdown-menu__divider"></div>
								<a href="/mod/log" class="dropdown-menu__item"><span>🛡️ Модерация</span></a>
							<?php endif; ?>

							<div class="dropdown-menu__divider"></div>
							<form class="dropdown-menu__item" action="<?= route('auth.logout') ?>" method="POST">
								<?= csrf_field() ?>
								<button type="submit" class="is-link bold">🚪 <?= __('logout') ?></button>
							</form>
						</div>

					</div>
				<?php else: ?>
					<a href="<?= route('auth.login') ?>"><?= __('login') ?></a>
					<?php if (config('invitations.config.invitations_enabled')): ?>
						<a class="nav-link" href="<?= route('home') ?>invite/request"><?= __('request_invitation') ?></a>
					<?php else: ?>
						<a class="mb-none btn-register" href="<?= route('auth.register') ?>"><?= __('register') ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<main> 
	    <?php if (\App\Modules\Common\Support\Layout::is('cabinet')): ?>
	    <div class="cabinet-layout">
	        <nav class="cabinet-sidebar">
	            <div class="cabinet-sidebar__user">
	                <?php if (!empty($currentUser['name'])): ?>
	                    <a href="<?= route('user.profile', ['username' => $currentUser['name']]) ?>" class="cabinet-sidebar__link">
	                        <?php if (!empty($currentUser['avatar'])): ?>
	                            <img src="/uploads/avatars/<?= substr($currentUser['avatar'], 0, 2) ?>/<?= e($currentUser['avatar']) ?>" class="mini-avatar-img" alt="">
	                        <?php else: ?>
	                            <span class="mini-avatar-placeholder"><?= e(mb_substr($currentUser['name'] ?? '?', 0, 1)) ?></span>
	                        <?php endif; ?>
	                        <span><?= e($currentUser['name'] ?? '') ?></span>
	                    </a>
	                <?php endif; ?>
	            </div>
	            <div class="cabinet-sidebar__nav">
	                <?php $__uri = $_SERVER['REQUEST_URI'] ?? ''; ?>
	                <a href="<?= route('stories.subscribed') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/subscribed') ? 'is-active' : '' ?>">📡 Подписки</a>
	                <a href="<?= route('user.stats') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/user/stats') ? 'is-active' : '' ?>">📊 Статистика</a>
	                <a href="<?= route('user.history') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/user/history') ? 'is-active' : '' ?>">📖 История чтения</a>
	                <a href="<?= route('saved.index') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/saved') ? 'is-active' : '' ?>">🔖 Закладки</a>
	                <a href="<?= route('drafts.index') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/drafts') ? 'is-active' : '' ?>">📝 Черновики</a>
	                <a href="<?= route('messages.index') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/messages') ? 'is-active' : '' ?>">✉️ Сообщения</a>
	                <a href="<?= route('notifications.index') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/notifications') ? 'is-active' : '' ?>">🔔 Уведомления</a>
	                <a href="/muted" class="cabinet-sidebar__link <?= str_contains($__uri, '/muted') ? 'is-active' : '' ?>">🔇 Игнорируемые</a>
	                <div class="cabinet-sidebar__divider"></div>
	                <a href="<?= route('user.profile.collections', ['username' => $currentUser['name']]) ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/collections') ? 'is-active' : '' ?>">📚 Коллекции</a>
	                <a href="<?= route('account.settings') ?>" class="cabinet-sidebar__link <?= str_contains($__uri, '/account/settings') ? 'is-active' : '' ?>">⚙️ Настройки</a>
	            </div>
	        </nav>
	        <div class="cabinet-content">
	            <?php if (!empty($errors->allErrors())): ?>
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
	        </div>
	    </div>
	    <?php else: ?>
	     <div class="content <?= $layoutClass ?>">
			<?php if (!empty($errors->allErrors())): ?>
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
		</div>
		<?php endif; ?>
	</main>

	<footer>
		<?php if (!\App\Modules\Common\Support\Layout::is('cabinet')): ?>
		 <nav>
				<a href="<?= route('home') ?>"><?= __('home') ?></a>
				<a href="/t/meta/wiki/about"><?= __('about') ?></a>
				
				<a href="<?= route('stories.staffPicks') ?>">Выбор редакции</a>
				
				<a href="<?= route('stats.index') ?>"><?= __('statistics') ?></a>
				<?php if (!empty($currentUser['isLoggedIn'])): ?>
					<a href="<?= route('tags.filters') ?>"><?= __('filters') ?></a>
				<?php endif; ?>

				<a href="/rss" title="RSS лента">RSS</a>
			<br>
			<?php if (!empty($currentUser['isAdmin'])): ?>
				<?= \W3a\Core\Support\Benchmark::renderStats() ?>
			<?php endif; ?>	
		 </nav>
		<?php endif; ?>
	</footer>

	<script src="<?= \W3a\Core\View\Asset::js() ?>"></script>

	<dialog id="login-modal">
		<div class="modal__body modal__body--center">
			<h2 class="modal__title">Войдите в аккаунт</h2>
			<p class="modal__text">Чтобы подписываться на авторов, нужно авторизоваться.</p>
			<a href="<?= route('auth.login') ?>" class="btn btn-pill btn-primary">Войти</a>
			<button type="button" class="btn btn-pill btn-outline" data-close-modal>Отмена</button>
		</div>
	</dialog>

	<script nonce="<?= csp_nonce(); ?>">
		document.addEventListener('click', function(e) {
			var btn = e.target.closest('[data-login-modal]');
			if (btn) {
				e.preventDefault();
				e.stopPropagation();
				document.getElementById('login-modal').showModal();
			}
			var close = e.target.closest('[data-close-modal]');
			if (close) {
				close.closest('dialog').close();
			}
		});
	</script>
	
</body>

</html>