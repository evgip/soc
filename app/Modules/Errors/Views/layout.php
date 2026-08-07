<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'error') ?></title>
    <link rel="stylesheet" href="/assets/css/app.min.css">
</head>
<body>
    <main>
		<?= $content ?>
    </main>
</body>
</html>
