<?php
/**
 * @var string $title
 * @var array  $filters
 * @var array  $allTags
 */
?>
<div>
    <h1>Фильтры тегов</h1>
    <p class="hint">
        Истории с отфильтрованными тегами не будут отображаться в вашей ленте.
    </p>

    <h2>Активные фильтры</h2>
    <?php if (empty($filters)): ?>
        <div class="alert alert-notice empty-state">
            <p>У вас пока нет активных фильтров.</p>
        </div>
    <?php else: ?>
        <div class="filters-list">
            <?php foreach ($filters as $filter): ?>
                <div class="filter-item">
					 <a href="<?= route('tags.filter', ['tagslug' => $filter['slug']]) ?>" class="tag tag-<?= e($filter['slug']); ?>">
                        <?= e($filter['name']) ?>
                    </a>
                    
                    <?php if (!empty($filter['description'])): ?>
                        <span class="tag-description hint">
                            — <?= e($filter['description']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <form action="/filters/remove" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tag_id" value="<?= e($filter['tag_id']) ?>">
                        <button type="submit" class="btn-remove-filter delete" 
                                onclick="return confirm('Удалить этот тег из фильтров?')">
                            удалить
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Добавить фильтр</h2>
    <form action="/filters/add" method="POST" class="form-field-group form-group">
        <?= csrf_field() ?>
        
        <select name="tag_id" required>
            <option value="">Выберите тег для скрытия...</option>
            <?php foreach ($allTags as $tag): ?>
                <?php 
                // Исключаем теги, которые уже в фильтрах
                $isFiltered = false;
                foreach ($filters as $f) {
                    if ($f['tag_id'] == $tag['id']) {
                        $isFiltered = true;
                        break;
                    }
                }
                if (!$isFiltered): 
                ?>
                    <option value="<?= e($tag['id']) ?>">
                        <?= e($tag['name']) ?> (<?= (int)($tag['stories_count'] ?? 0) ?>)
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn-add-filter">
            Добавить
        </button>
    </form>
</div>