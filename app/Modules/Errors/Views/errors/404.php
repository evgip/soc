<div class="alert is-notice">
    <h2>🔍 Страница не найдена (404)</h2>
    
    <p class="hint">
        Страница, которую вы пытаетесь открыть, отсутствует по указанному URL-адресу 
        или была удалена.
    </p>

    <?php if (!empty($message)): ?>  
        <p class="mt-4">
            <span class="red bold">Детали:</span> <?= $message; ?>
        </p>
    <?php endif; ?>

    <div class="flex gap mt-4">
        <a href="/" class="tag-checkbox">🏠 Вернуться на главную</a>
        <a href="/tags" class="tag-checkbox">🏷️ Популярные теги</a>
    </div>
</div>