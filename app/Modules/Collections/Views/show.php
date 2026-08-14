<?php
declare(strict_types=1);

/**
 * Страница коллекции (оглавление серии статей).
 * 
 * @var array $collection   Данные коллекции + cover_url
 * @var array $profileUser  Данные автора коллекции
 * @var bool  $isOwner      Текущий пользователь - владелец коллекции
 */

$stories = $collection['stories'] ?? [];
$totalStories = count($stories);
?>

<div class="container">
    <!-- ============================================
         ШАПКА КОЛЛЕКЦИИ С ОБЛОЖКОЙ
         ============================================ -->
    <div class="collection-show-header">
        <!-- Обложка (если есть) -->
        <?php if (!empty($collection['cover_url'])): ?>
            <div class="collection-show-header__cover">
                <img src="<?= e($collection['cover_url']) ?>" 
                     alt="<?= e($collection['title']) ?>" 
                     class="collection-show-header__cover-image"
                     loading="eager">
            </div>
        <?php endif; ?>

        <!-- Информация о коллекции -->
        <div class="collection-show-header__info">
            <p class="collection-show-header__label">📚 Коллекция</p>
            <h1><?= e($collection['title']) ?></h1>

            <?php if (!empty($collection['description'])): ?>
                <p class="collection-show-header__description"><?= e($collection['description']) ?></p>
            <?php endif; ?>

            <div class="collection-show-header__meta">
                <a href="<?= route('user.profile', ['username' => $profileUser['username']]) ?>">
                    <?= e($profileUser['username']) ?>
                </a>
                <span class="divider">•</span>
                <span><?= $totalStories ?> <?= plural($totalStories, ['статья', 'статьи', 'статей']) ?></span>
                <?php if (!empty($collection['created_at'])): ?>
                    <span class="divider">•</span>
                    <span>Создана <?= adaptive_time($collection['created_at']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Действия владельца -->
        <?php if ($isOwner): ?>
            <div class="collection-show-header__actions">
                <button type="button" class="btn btn-primary btn-pill" id="btn-add-story">
                    + Добавить статью
                </button>
                <a href="<?= route('collections.edit', ['id' => $collection['id']]) ?>" class="btn btn-outline-secondary btn-pill">
                    ✏️ Редактировать
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================
         ОГЛАВЛЕНИЕ КОЛЛЕКЦИИ
         ============================================ -->
    <?php if (empty($stories)): ?>
        <div class="empty-state">
            <h2>В коллекции пока нет статей</h2>
            <?php if ($isOwner): ?>
                <p>Добавьте свои статьи, чтобы начать формировать серию.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ol class="collection-toc">
            <?php foreach ($stories as $index => $story): ?>
                <?php 
                $storyUrl = route('story.show', ['id' => $story['story_id']]);
                
                // Формируем excerpt из description_text
                $excerpt = '';
                if (!empty($story['description_text'])) {
                    $clean = strip_tags((string) $story['description_text']);
                    $clean = preg_replace('/\s+/', ' ', $clean); // нормализуем пробелы
                    $clean = trim($clean);
                    $excerpt = mb_strlen($clean) > 180 
                        ? mb_substr($clean, 0, 180) . '…' 
                        : $clean;
                }
                
                // 🔒 Защита от NULL: используем story_created_at (COALESCE(published_at, created_at))
                $storyDate = $story['story_created_at'] ?? null;
                ?>
                <li class="collection-toc__item" data-story-id="<?= (int) $story['story_id'] ?>">
                    <span class="collection-toc__number"><?= $index + 1 ?></span>

                    <div class="collection-toc__content">
                        <h3 class="collection-toc__title">
                            <a href="<?= $storyUrl ?>"><?= e($story['title']) ?></a>
                        </h3>
                        
                        <?php if ($excerpt): ?>
                            <p class="collection-toc__excerpt"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                        
                        <div class="collection-toc__meta">
                            <!-- 🆕 Дата публикации с защитой от NULL -->
                            <?php if ($storyDate): ?>
                                <span><?= adaptive_time($storyDate) ?></span>
                                <span class="divider">•</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($story['reading_time'])): ?>
                                <span>⏱ <?= (int) $story['reading_time'] ?> мин</span>
                                <span class="divider">•</span>
                            <?php endif; ?>
                            
                            <span>💬 <?= (int) $story['comments_count'] ?></span>
                            <span class="divider">•</span>
                            <span>▲ <?= (int) $story['score'] ?></span>
                        </div>
                    </div>

                    <?php if ($isOwner): ?>
                        <button type="button" 
                                class="icon-btn collection-toc__remove" 
                                data-story-id="<?= (int) $story['story_id'] ?>"
                                title="Удалить из коллекции"
                                aria-label="Удалить статью из коллекции">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<?php if ($isOwner): ?>
<!-- ============================================
     МОДАЛЬНОЕ ОКНО ДОБАВЛЕНИЯ СТАТЬИ
     ============================================ -->
<div class="collection-modal" id="collection-modal">
    <div class="collection-modal__backdrop"></div>
    <div class="collection-modal__content">
        <div class="collection-modal__header">
            <h3>Добавить статью в коллекцию</h3>
            <button type="button" class="icon-btn" id="btn-close-modal" aria-label="Закрыть">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="collection-modal__body">
            <div id="available-stories-list">
                <p class="hint">Загрузка статей...</p>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce(); ?>">
/**
 * Управление коллекцией (добавление/удаление статей)
 */
(function() {
    'use strict';

    const collectionId = <?= (int) $collection['id'] ?>;
    const modal = document.getElementById('collection-modal');
    const btnAddStory = document.getElementById('btn-add-story');
    const btnCloseModal = document.getElementById('btn-close-modal');
    const storiesList = document.getElementById('available-stories-list');

    if (!modal) return;

    function openModal() {
        modal.classList.add('is-open');
        loadAvailableStories();
    }

    function closeModal() {
        modal.classList.remove('is-open');
    }

    btnAddStory?.addEventListener('click', openModal);
    btnCloseModal?.addEventListener('click', closeModal);
    modal.querySelector('.collection-modal__backdrop')?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    // Загрузка списка статей автора
    async function loadAvailableStories() {
        try {
            const resp = await fetch(`/collections/${collectionId}/stories/available`);
            const data = await resp.json();

            if (!data.success) {
                storiesList.innerHTML = '<p class="hint">Ошибка загрузки</p>';
                return;
            }

            if (!data.stories || data.stories.length === 0) {
                storiesList.innerHTML = '<p class="hint">У вас нет опубликованных статей</p>';
                return;
            }

            storiesList.innerHTML = data.stories.map(story => `
                <div class="available-story-item ${story.in_collection ? 'is-in-collection' : ''}">
                    <div class="available-story-item__info">
                        <span class="available-story-item__title">${escapeHtml(story.title)}</span>
                        ${story.in_collection ? '<span class="available-story-item__badge">✓ В коллекции</span>' : ''}
                    </div>
                    ${story.in_collection 
                        ? `<button type="button" class="btn btn-sm btn-outline-secondary btn-remove-story" data-story-id="${story.id}">Убрать</button>`
                        : `<button type="button" class="btn btn-sm btn-primary btn-add-to-collection" data-story-id="${story.id}">Добавить</button>`
                    }
                </div>
            `).join('');

            // Обработчики добавления/удаления
            storiesList.querySelectorAll('.btn-add-to-collection').forEach(btn => {
                btn.addEventListener('click', () => toggleStory(btn.dataset.storyId, 'add'));
            });

            storiesList.querySelectorAll('.btn-remove-story').forEach(btn => {
                btn.addEventListener('click', () => toggleStory(btn.dataset.storyId, 'remove'));
            });
        } catch (e) {
            console.error(e);
            storiesList.innerHTML = '<p class="hint">Ошибка сети</p>';
        }
    }

    // Добавить/удалить статью
    async function toggleStory(storyId, action) {
        const url = action === 'add' 
            ? `/collections/${collectionId}/stories/add`
            : `/collections/${collectionId}/stories/remove`;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.content : '';

            const formData = new FormData();
            formData.append('story_id', storyId);
            if (csrfToken) formData.append('csrf_token', csrfToken);

            const resp = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await resp.json();

            if (data.success) {
                await loadAvailableStories();
                setTimeout(() => location.reload(), 500);
            } else {
                alert(data.error || 'Ошибка');
            }
        } catch (e) {
            console.error(e);
            alert('Ошибка сети');
        }
    }

    // Удаление статьи из коллекции (на странице оглавления)
    document.querySelectorAll('.collection-toc__remove').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Удалить статью из коллекции?')) return;

            const storyId = btn.dataset.storyId;

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : '';

                const formData = new FormData();
                formData.append('story_id', storyId);
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const resp = await fetch(`/collections/${collectionId}/stories/remove`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await resp.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Ошибка');
                }
            } catch (e) {
                console.error(e);
            }
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
<?php endif; ?>