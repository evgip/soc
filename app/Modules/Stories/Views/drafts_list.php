<h1>Мои черновики</h1>

<p>
    <a href="/stories/create" class="btn btn--primary">+ Начать писать</a>
</p>

<?php if (empty($drafts)): ?>
    <div class="alert">
        <p>У вас пока нет черновиков</p>
    </div>
<?php else: ?>
    <?php foreach ($drafts as $draft): ?>
        <article class="draft-card">
            <h3>
                <a href="/stories/<?= (int)$draft['id'] ?>/edit">
                    <?= e($draft['title'] ?: 'Без названия') ?>
                </a>
            </h3>
            
            <?php if (!empty($draft['description_text'])): ?>
                <p class="hint">
                    <?= e(mb_substr($draft['description_text'], 0, 150)) ?>...
                </p>
            <?php endif; ?>

            <p class="hint">
                🕒 Обновлён: <?= date('d.m.Y H:i', strtotime($draft['updated_at'])) ?>
            </p>

            <div class="form-actions v-center">
                <a href="/stories/<?= (int)$draft['id'] ?>/edit" class="btn btn--small">
                    Продолжить
                </a>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>