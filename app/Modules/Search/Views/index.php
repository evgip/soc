<?php
// ✅ Все данные приходят из контроллера, не создаём модели здесь
$currentUserId = $currentUserId ?? 0;
$canUserDownvote = $canUserDownvote ?? false;
$currentVotes = $currentVotes ?? [];
?>

<h1>Поиск</h1>

<form action="/search" method="GET">
    <p>
        <input type="text" name="q" value="<?= e($query) ?>" 
               placeholder="Поисковый запрос..." required autofocus
			   class="w-60">
        <button type="submit">Искать</button>
    </p>

    <p class="hint">
        Искать в:
        <label><input type="radio" name="what" value="stories" <?= $what === 'stories' ? 'checked' : '' ?>> статьях</label>
        <label><input type="radio" name="what" value="comments" <?= $what === 'comments' ? 'checked' : '' ?>> комментариях</label>
        &nbsp;&nbsp;
        Сортировка:
        <label><input type="radio" name="order" value="relevance" <?= $sortBy === 'relevance' ? 'checked' : '' ?>> по релевантности</label>
        <label><input type="radio" name="order" value="date" <?= $sortBy === 'date' ? 'checked' : '' ?>> по дате</label>
    </p>
</form>

<?php if (!empty($query) && strlen($query) >= 3): ?>

    <hr>

    <p class="hint">
        Найдено: <strong><?= count($results) ?></strong>
        <?php if (!empty($results)): ?>
            — в <?= $what === 'stories' ? 'статьях' : 'комментариях' ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($results)): ?>

        <?php if ($what === 'stories'): ?>
            <!-- Результаты: СТАТЬИ -->
            <ol class="stories">
                <?php foreach ($results as $story): ?>
                    <?php partial('Stories::_story_item', [
                        'story'         => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin'       => $isAdmin ?? false,
                        'canUserDownvote' => $canUserDownvote,
                        'currentVotes'  => $currentVotes,
                        'newCommentsMap'=> $newCommentsMap ?? [],
                        'hideAuthor'    => false,
                        'relevance'     => $story['relevance'] ?? null,
                    ]); ?>
                <?php endforeach; ?>
            </ol>

        <?php else: ?>
            <!-- Результаты: КОММЕНТАРИИ -->
            <ol class="comments">
                <?php foreach ($results as $comment): ?>
                    <li class="comment">
                        <div class="byline">
                            📌 В теме:
                            <a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#comment-block-<?= $comment['id'] ?>">
                                <strong><?= e($comment['story_title']) ?></strong>
                            </a>
                        </div>

                        <div class="comment_text">
                            <?= markdown_comment($comment['comment']) ?>
                        </div>

                        <div class="byline">
                            <?php if (!empty($comment['author_avatar'])): ?>
                                <img src="/uploads/avatars/<?= substr($comment['author_avatar'], 0, 2) ?>/<?= e($comment['author_avatar']) ?>" class="avatar" alt="">
                            <?php endif; ?>

                            <a href="<?= route('user.profile', ['username' => $comment['author_name']]) ?>">
                                <?= e($comment['author_name']) ?>
                            </a>

                            <span class="divider">|</span>
                            <span>оценка: <strong><?= (int)$comment['score'] ?></strong></span>

                            <span class="divider">|</span>
                            <span><?= e(date('d.m.Y H:i', strtotime($comment['created_at']))) ?></span>

                            <?php if (isset($comment['relevance'])): ?>
                                <span class="divider">|</span>
                                <span class="hint">релевантность: <?= round($comment['relevance'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

    <?php else: ?>
        <p class="hint">Ничего не найдено. Попробуйте изменить запрос.</p>
    <?php endif; ?>

<?php endif; ?>