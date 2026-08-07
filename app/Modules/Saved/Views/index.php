<?php
// app/Modules/Saved/Views/index.php

$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$canUserDownvote = $canUserDownvote ?? false;
$currentVotes = $currentVotes ?? [];
$newCommentsMap = $newCommentsMap ?? [];
$stories = $stories ?? [];
?>

<div class="container">
    <h1>📚 Мои закладки</h1>
    
    <?php if (empty($stories)): ?>
        <p class="hint">У вас пока нет сохранённых историй. Нажмите 🔖 на любой истории, чтобы добавить её в закладки.</p>
    <?php else: ?>
        <ol class="stories">
            <?php foreach ($stories as $story): ?>
                <?php
                $isStoryDeleted = !empty($story['deleted_at']);
                $newCount = $newCommentsMap[$story['id']] ?? 0;
                ?>
                
                <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">
                    <?php partial('Votes::_voters', [
                        'type' => 'story',
                        'id' => (int)$story['id'],
                        'score' => (int)$story['score'],
                        'currentVoteState' => $currentVotes[$story['id']] ?? null,
                        'canDownvote' => $canUserDownvote,
                        'isLoggedIn' => $currentUserId > 0,
                        'contentOwnerId' => (int)$story['user_id'],
                    ]); ?>
                    
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
                            
                            <?php if (!empty($story['tags'])): ?>
                                <span class="tags">
                                    <?php foreach ($story['tags_with_names'] as $tagData): ?>
                                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag tag-<?= e($tagData['slug']); ?>"><?= e($tagData['name']) ?></a>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php partial('Stories::_story_meta', [
                            'story' => $story,
                            'currentUserId' => $currentUserId,
                            'isAdmin' => $isAdmin,
                            'newCount' => $newCount,
                            'isSavedPage' => true, // Флаг для отображения кнопки "убрать из закладок"
                        ]); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        
        <?php if (isset($totalPages) && $totalPages > 1): ?>
            <?= pagination($currentPage, $totalPages) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>