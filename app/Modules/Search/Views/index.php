<?php
// ✅ Все данные приходят из контроллера, не создаём модели здесь
$currentUserId = $currentUserId ?? 0;
$canUserDownvote = $canUserDownvote ?? false;
$currentVotes = $currentVotes ?? [];
?>

<h1>Поиск</h1>

<form action="/search" method="GET">
    <div class="search-form-row">
        <input type="text" name="q" value="<?= e($query) ?>" 
               placeholder="Поисковый запрос..." required autofocus>
        <button type="submit">Искать</button>
    </div>

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
                    <li class="story">

                        <!-- Голосование -->
                        <?php partial('Votes::_voters', [
                            'type' => 'story',
                            'id' => (int)$story['id'],
                            'score' => (int)$story['score'],
                            'currentVoteState' => $currentVotes[$story['id']] ?? null,
                            'canDownvote' => $canUserDownvote,
                            'isLoggedIn' => $currentUserId > 0,
                            'contentOwnerId' => (int)$story['user_id'],
                        ]); ?>

                        <!-- Контент -->
                        <div class="story_liner">
                            <div class="link">
                                <?php
                                $isExternal = !empty($story['url']);
                                $targetUrl = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
                                ?>
                                <a href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                                    <?= e($story['title']) ?>
                                </a>
                                <?php if ($isExternal): ?>
                                    <?php 
                                    $domainHost = !empty($story['url']) ? parse_url($story['url'], PHP_URL_HOST) : null;
                                    if ($domainHost): 
                                    ?>
                                        <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>" class="domain">
                                            <?= e($domainHost) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($story['tags'])): ?>  
                                <span class="tags">
                                    <?php foreach ($story['tags_with_names'] as $tagData): ?>  
                                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag"><?= e($tagData['name']) ?></a>
                                    <?php endforeach; ?>
                                </span> 
                            <?php endif; ?>

                            <div class="byline">
                                <?php if (!empty($story['author_avatar'])): ?>
                                    <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" class="avatar" alt="">
                                <?php endif; ?>

                                <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>">
                                    <?= e($story['author_name']) ?>
                                </a>

                                <span class="divider">|</span>
                                <span><?= e(date('d.m.Y H:i', strtotime($story['created_at']))) ?></span>

                                <span class="divider">|</span>
                                <a href="<?= route('story.show', ['id' => $story['id']]) ?>">
                                    <?php if ((int)$story['comments_count'] === 0): ?>
                                        нет комментариев
                                    <?php else: ?>
                                        <?= (int)$story['comments_count'] ?> комм.
                                    <?php endif; ?>
                                </a>

                                <?php if (isset($story['relevance'])): ?>
                                    <span class="divider">|</span>
                                    <span class="hint">релевантность: <?= round($story['relevance'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
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