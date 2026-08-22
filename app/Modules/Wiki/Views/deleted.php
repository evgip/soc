<?= $breadcrumbs ?>

<div class="hint">
    Удалённые wiki страницы тега <a href="/t/<?= e($tag['slug']) ?>" class="tag tag-<?= e($tag['slug']) ?>"><?= e($tag['name']) ?></a>.
    <br>Удалённые страницы можно восстановить в течение неограниченного времени.
</div>

<?php if (empty($deletedPages)): ?>
    <div class="hint">
        <p>Нет удалённых wiki страниц.</p>
    </div>
<?php else: ?>
    <ol class="stories">
        <?php foreach ($deletedPages as $page): ?>
            <li class="story story--simple">
                <div class="story_liner">
                    <div class="link">
                        <a href="/t/<?= e($tag['slug']) ?>/wiki/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a>
                        <span class="tag tag-meta">Удалена</span>
                    </div>
                    
                    <div class="byline">
                        👤 <a href="/user/<?= e($page['author_name']) ?>"><?= e($page['author_name']) ?></a>
                        <span class="divider">|</span>
                        <span title="<?= dt($page['deleted_at'], 'd.m.Y H:i:s') ?>">
                            🗑️ Удалена: <?= dt($page['deleted_at']) ?>
                        </span>
                        <span class="divider">|</span>
                        <form action="/t/<?= e($tag['slug']) ?>/wiki/<?= $page['id'] ?>/restore" 
                              method="POST" 
                              class="inline-form js-confirm-delete"
                              data-confirm-message="Восстановить эту страницу?">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="is-link">♻️ Восстановить</button>
                        </form>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>