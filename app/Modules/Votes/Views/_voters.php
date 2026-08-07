<?php
/**
 * Компонент голосования
 * @var string $type
 * @var int    $id
 * @var int    $score
 * @var int    $currentVoteState
 * @var bool   $canDownvote
 * @var bool   $isLoggedIn
 * @var int    $contentOwnerId
 * @var bool   $inline (опционально: компактный вид для страницы статьи)
 */

$inline = $inline ?? false;
$request = new \W3a\Core\Http\Request();
$isOwnContent = $isLoggedIn && ($contentOwnerId === (int)($_SESSION['user_id'] ?? 0));
?>

<div class="voters <?= $inline ? 'voters--inline' : '' ?>">
    <?php if ($isLoggedIn && !$isOwnContent): ?>
        <!-- Кнопка "ЗА" -->
        <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'up']) ?>" 
              method="POST" 
              data-vote-form 
              data-direction="up"
              class="inline-form">
            <?= $request->csrfField() ?>
            <button type="submit" 
                    class="upvoter <?= $currentVoteState === 1 ? 'upvoted' : '' ?>" 
                    title="Интересно"
                    aria-label="Голос за">
                <span class="upvoter-icon">▲</span>
            </button>
        </form>
        
        <span class="score"><?= $score ?></span>

        <!-- Кнопка "ПРОТИВ" -->
        <?php if ($canDownvote): ?>
            <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'down']) ?>" 
                  method="POST" 
                  data-vote-form 
                  data-direction="down"
                  class="inline-form">
                <?= $request->csrfField() ?>
                <button type="submit" 
                        class="upvoter <?= $currentVoteState === -1 ? 'upvoted' : '' ?>" 
                        title="Не интересно"
                        aria-label="Голос против">
                    <span class="upvoter-icon">▼</span>
                </button>
            </form>
        <?php endif; ?>
        
    <?php elseif ($isOwnContent): ?>
        <span class="upvoter disabled" title="Вы не можете голосовать за свой контент">▲</span>
        <span class="score"><?= $score ?></span>
        <span class="upvoter disabled" title="Вы не можете голосовать за свой контент">▼</span>
    <?php else: ?>
        <span class="upvoter disabled" title="Войдите, чтобы голосовать">▲</span>
        <span class="score"><?= $score ?></span>
        <span class="upvoter disabled" title="Войдите, чтобы голосовать">▼</span>
    <?php endif; ?>
</div>