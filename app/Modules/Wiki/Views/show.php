<?php
/**
 * Wiki show - отображение wiki страницы
 * Дизайн в стиле Stories show
 */

// ✅ Все данные приходят из контроллера
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>

<?= $breadcrumbs ?>

<ol class="stories">
    <li class="story">
        <div class="story_liner">
            <div class="link">
                <h1 style="margin: 0; font-size: 1.4em; display: inline;">
                    <?= e($page['title']) ?>
                </h1>
                <?php if (!empty($page['is_primary'])): ?>
                    <span class="tag tag-meta">Основная страница</span>
                <?php endif; ?>
            </div>
            
            <div class="byline" style="margin-bottom: 1em;">
                👤 Автор: <a href="/user/<?= e($page['author_name']) ?>"><?= e($page['author_name']) ?></a>
                <span class="divider">|</span>
                <span title="<?= e(date('d.m.Y H:i:s', strtotime($page['updated_at']))) ?>">
                    📅 Обновлено: <?= e(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
                </span>
                <span class="divider">|</span>
                👁️ <?= (int)$page['view_count'] ?> <?= plural((int)$page['view_count'], ['просмотр', 'просмотра', 'просмотров']) ?>
                
                <?php if ($canEdit || $canDelete): ?>
                    <span class="divider">|</span>
                    <?php if ($canEdit): ?>
                        <a href="/t/<?= e($tag['slug']) ?>/wiki/<?= (int)$page['id'] ?>/edit">✏️ Редактировать</a>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                        <span class="divider">|</span>
                        <form action="/t/<?= e($tag['slug']) ?>/wiki/<?= (int)$page['id'] ?>/delete" 
                              method="POST" 
                              class="inline-form js-confirm-delete"
                              data-confirm-message="Вы уверены, что хотите удалить эту страницу?">
                            <?= csrf_field() ?>
                            <button type="submit" class="is-link delete">🗑️ Удалить</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="story_content">
                <?= $page['rendered_content'] ?>
            </div>

            <div class="byline" style="margin-top: 1em; padding-top: 0.5em; border-top: 1px solid var(--color-box-bg-shaded);">
                <a href="/t/<?= e($tag['slug']) ?>/wiki">← Назад к wiki тега #<?= e($tag['name']) ?></a>
                
                <?php if ($page['created_at'] != $page['updated_at']): ?>
                    <span class="divider">|</span>
                    <span class="hint">
                        Создано: <?= e(date('d.m.Y H:i', strtotime($page['created_at']))) ?> | 
                        Последнее изменение: <?= e(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </li>
</ol>