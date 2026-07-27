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
 */

$request = new \W3a\Core\Request();
$isOwnContent = $isLoggedIn && ($contentOwnerId === (int)($_SESSION['user_id'] ?? 0));
?>
<div class="voters">
    <?php if ($isLoggedIn && !$isOwnContent): ?>
        
        <!-- Кнопка "ЗА" -->
        <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'up']) ?>" 
              method="POST" 
              data-vote-form 
              data-direction="up">
            <?= $request->csrfField() ?>
            <button type="submit" 
                    class="upvoter <?= $currentVoteState === 1 ? 'upvoted' : '' ?>" 
                    title="Интересно">▲</button>
        </form>
        
        <div class="score"><?= $score ?></div>

        <!-- Кнопка "ПРОТИВ" -->
        <?php if ($canDownvote): ?>
            <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'down']) ?>" 
                  method="POST" 
                  data-vote-form 
                  data-direction="down">
                <?= $request->csrfField() ?>
                <button type="submit" 
                        class="upvoter <?= $currentVoteState === -1 ? 'upvoted' : '' ?>" 
                        title="Не интересно">▼</button>
            </form>
        <?php endif; ?>
        
    <?php elseif ($isOwnContent): ?>
        <!-- Свой контент: только score, без кнопок -->
        <span class="upvoter" title="Вы не можете голосовать за свой контент">▲</span>
        <div class="score"><?= $score ?></div>
        <span class="upvoter" title="Вы не можете голосовать за свой контент">▼</span>
    <?php else: ?>
        <!-- Гость -->
        <span class="upvoter" title="Войдите, чтобы голосовать">▲</span>
        <div class="score"><?= $score ?></div>
        <span class="upvoter" title="Войдите, чтобы голосовать">▼</span>
    <?php endif; ?>
</div>