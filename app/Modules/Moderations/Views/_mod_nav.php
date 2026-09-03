<nav class="nav br-none" aria-label="Модерация">
    <a href="/mod/log" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/log') ? 'is-active' : '' ?>">📋 Лог</a>
    <a href="/mod/notes" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/notes') ? 'is-active' : '' ?>">🔒 Заметки</a>
    <a href="/mod/suggestions" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/suggestions') ? 'is-active' : '' ?>">
        💡 Предложения
        <?php if (($activeSuggestionsCount ?? 0) > 0): ?>
            <span class="nav-badge-counter" style="font-size:0.65rem;min-width:16px;height:16px;padding:0 4px;"><?= (int)$activeSuggestionsCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/mod/stats" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/stats') ? 'is-active' : '' ?>">📈 Активность</a>
    <a href="/admin/flags" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/flags') ? 'is-active' : '' ?>">🚩 Жалобы</a>
</nav>