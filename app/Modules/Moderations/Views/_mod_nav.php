<nav class="mod-nav">
    <a href="/mod/log" class="mod-nav__link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/log') ? 'mod-nav__link--active' : '' ?>">📋 Лог</a>
    <a href="/mod/notes" class="mod-nav__link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/notes') ? 'mod-nav__link--active' : '' ?>">🔒 Заметки</a>
    <a href="/mod/suggestions" class="mod-nav__link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/suggestions') ? 'mod-nav__link--active' : '' ?>">
        💡 Предложения
        <?php if (($activeSuggestionsCount ?? 0) > 0): ?>
            <span class="nav-badge-counter" style="font-size:0.65rem;min-width:16px;height:16px;padding:0 4px;"><?= (int)$activeSuggestionsCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/mod/stats" class="mod-nav__link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/mod/stats') ? 'mod-nav__link--active' : '' ?>">📈 Активность</a>
    <a href="/admin/flags" class="mod-nav__link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/flags') ? 'mod-nav__link--active' : '' ?>">🚩 Жалобы</a>
</nav>