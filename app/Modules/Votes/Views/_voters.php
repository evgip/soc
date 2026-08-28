<?php
/**
 * Компонент голосования
 * @var string $type       'story' или 'comment'
 * @var int    $id
 * @var int    $score
 * @var int    $userClaps  0–50 для stories, 0/1 для comments
 * @var bool   $isLoggedIn
 * @var bool   $inline
 */

$inline = $inline ?? false;
$isComment = $type === 'comment';
$request = new \W3a\Core\Http\Request();
$routeName = $isComment ? 'votes.comment_like' : 'votes.clap';
$action = route($routeName, ['id' => $id]);
$liked = $isComment && $userClaps > 0;
?>

<div class="clappers <?= $inline ? 'clappers--inline' : '' ?>">
    <?php if ($isLoggedIn): ?>
        <form action="<?= $action ?>"
              method="POST"
              data-clap-form
              data-type="<?= $type ?>"
              class="inline-form">
            <?= $request->csrfField() ?>
            <button type="submit"
                    class="clap-btn <?= $liked ? 'clap-btn--active' : '' ?>"
                    data-user-claps="<?= $userClaps ?>"
                    title="<?= $isComment ? 'Нравится' : 'Хлопнуть' ?>"
                    aria-label="<?= $isComment ? 'Нравится' : 'Хлопнуть' ?>">
                <span class="clap-icon"><?= $isComment ? '❤️' : '👏' ?></span>
                <span class="clap-score"><?= $score ?></span>
            </button>
        </form>
    <?php else: ?>
        <button class="clap-btn clap-btn--disabled" title="<?= $isComment ? 'Войдите, чтобы оценить' : 'Войдите, чтобы хлопать' ?>">
            <span class="clap-icon"><?= $isComment ? '❤️' : '👏' ?></span>
            <span class="clap-score"><?= $score ?></span>
        </button>
    <?php endif; ?>
</div>