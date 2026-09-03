<?php
/**
 * История чтения
 * @var array $stories
 */
$currentUserId = \W3a\Core\Auth\Auth::check() ? \W3a\Core\Auth\Auth::id() : 0;
?>
<div class="content content-medium">

    <h1>История чтения</h1>

    <?php if (!empty($stories)): ?>
        <ul class="story-list">
            <?php foreach ($stories as $story): ?>
                <?php partial('Stories::_story_row', [
                    'story'         => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin'       => false,
                    'currentVotes'  => [],
                    'newCommentsMap'=> [],
                    'hideAuthor'    => false,
                ]); ?>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted mt-4">Вы ещё не читали статьи.</p>
    <?php endif; ?>

</div>