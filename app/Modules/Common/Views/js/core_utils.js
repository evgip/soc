/**
 * Глобальная функция для получения CSRF-токена
 * Используется для отправки токена в body формы (не в headers)
 */
window.getCsrfToken = function() {
    const name = 'XSRF-TOKEN=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(name) === 0) {
            return c.substring(name.length, c.length);
        }
    }
    return '';
};

/**
 * CSRF Protection - автоматическая отправка токена для AJAX-запросов
 * Double-Submit Cookie Pattern
 */
const CsrfProtection = {
    cookieName: 'XSRF-TOKEN',
    headerName: 'X-XSRF-TOKEN',

    getToken() {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === this.cookieName) {
                return decodeURIComponent(value);
            }
        }
        return null;
    },

    init() {
        this.interceptFetch();
        this.interceptXMLHttpRequest();
    },

    interceptFetch() {
        const originalFetch = window.fetch;
        const self = this;
        
        window.fetch = function(url, options = {}) {
            options.headers = options.headers || {};
            
            if (options.headers.constructor === Object) {
                const token = self.getToken();
                if (token) {
                    options.headers[self.headerName] = token;
                    options.headers['X-Requested-With'] = 'XMLHttpRequest';
                }
            }

            return originalFetch(url, options);
        };
    },

    interceptXMLHttpRequest() {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        const self = this;

        XMLHttpRequest.prototype.open = function(method, url) {
            this._csrfMethod = method;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function(data) {
            if (this._csrfMethod && !['GET', 'HEAD', 'OPTIONS'].includes(this._csrfMethod.toUpperCase())) {
                const token = self.getToken();
                if (token) {
                    this.setRequestHeader(self.headerName, token);
                    this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                }
            }
            return originalSend.apply(this, arguments);
        };
    }
};

// Инициализация CSRF при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    CsrfProtection.init();
});

/**
 * Управление темой (светлая/тёмная)
 * 
 * ПРИНЦИП РАБОТЫ:
 * - Атрибут data-theme ставится на <html>
 * - "dark" = тёмная тема
 * - "light" или отсутствие атрибута = светлая тема
 * - Приоритет: localStorage > системная настройка
 */
(function() {
    'use strict';
    
    const STORAGE_KEY = 'w3a_theme';
    
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        
        // Если пользователь не выбирал — используем системную настройку
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    function applyTheme(theme) {
        // Явно ставим атрибут для обеих тем
        // Это важно для CSS, где мы убрали @media (prefers-color-scheme: dark)
        document.documentElement.setAttribute('data-theme', theme);
    }
    
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem(STORAGE_KEY, next);
        
        // Логируем для отладки
        console.log(`[Theme] Switched to: ${next}`);
    }
    
    // Применяем тему сразу (до DOMContentLoaded, чтобы избежать мерцания)
    applyTheme(getPreferredTheme());
    
    // Вешаем обработчик на кнопку переключения
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleTheme);
        }
    });
})();

/**
 * Универсальное подтверждение действий
 * Работает для любого элемента с атрибутом data-confirm
 */
document.addEventListener('DOMContentLoaded', function() {
    // Делегирование событий для всех элементов с data-confirm
    document.addEventListener('click', function(e) {
        const element = e.target.closest('[data-confirm]');
        if (!element) return;
        
        const message = element.getAttribute('data-confirm');
        if (message && !confirm(message)) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});


/**
 * Dropdown меню пользователя в шапке
 */
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownWrapper = document.getElementById('user-dropdown-wrapper');
        if (!dropdownWrapper) return;
        
        const trigger = document.getElementById('user-dropdown-trigger');
        const menu = document.getElementById('user-dropdown-menu');
        
        if (!trigger || !menu) return;
        
        // Переключение dropdown
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            const isActive = menu.classList.toggle('active');
            trigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        });
        
        // Закрытие при клике вне dropdown
        document.addEventListener('click', function(e) {
            // Не закрываем, если клик был на trigger
            if (e.target === trigger || trigger.contains(e.target)) {
                return;
            }
            
            // Закрываем только если клик был вне wrapper
            if (!dropdownWrapper.contains(e.target)) {
                if (menu.classList.contains('active')) {
                    menu.classList.remove('active');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }
        });
        
        // Закрытие при Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && menu.classList.contains('active')) {
                menu.classList.remove('active');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    });
})();


/**
 * Story Menu (dropdown "три точки") — универсальный
 * Работает для всех dropdown на странице через делегирование событий
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // === 1. Переключение dropdown по клику на триггер ===
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.story-menu-trigger');
            
            if (trigger) {
                e.stopPropagation();
                const wrapper = trigger.closest('.story-menu-wrapper');
                const dropdown = wrapper.querySelector('.story-menu-dropdown');
                const isOpen = dropdown.classList.toggle('active');
                trigger.setAttribute('aria-expanded', isOpen);
                
                // Закрываем все другие открытые dropdown
                document.querySelectorAll('.story-menu-dropdown.active').forEach(other => {
                    if (other !== dropdown) {
                        other.classList.remove('active');
                        other.closest('.story-menu-wrapper')
                            .querySelector('.story-menu-trigger')
                            .setAttribute('aria-expanded', 'false');
                    }
                });
                return;
            }
            
            // === 2. Закрытие при клике вне dropdown ===
            if (!e.target.closest('.story-menu-wrapper')) {
                document.querySelectorAll('.story-menu-dropdown.active').forEach(dropdown => {
                    dropdown.classList.remove('active');
                    dropdown.closest('.story-menu-wrapper')
                        .querySelector('.story-menu-trigger')
                        .setAttribute('aria-expanded', 'false');
                });
            }
        });

        // === 3. Закрытие по Escape ===
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.story-menu-dropdown.active').forEach(dropdown => {
                    dropdown.classList.remove('active');
                    const trigger = dropdown.closest('.story-menu-wrapper')
                        .querySelector('.story-menu-trigger');
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                });
            }
        });

        // === 4. Копирование ссылки ===
        document.addEventListener('click', async function(e) {
            const button = e.target.closest('[data-copy-link]');
            if (!button) return;
            
            const url = button.getAttribute('data-copy-link');
            const fullUrl = window.location.origin + url;
            
            try {
                await navigator.clipboard.writeText(fullUrl);
                
                const originalText = button.innerHTML;
                button.innerHTML = '<span class="story-menu-item__icon">✅</span> Скопировано!';
                button.style.color = 'var(--color-fg-affirmative)';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.color = '';
                }, 2000);
                
                // Закрываем родительский dropdown
                const dropdown = button.closest('.story-menu-dropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                    dropdown.closest('.story-menu-wrapper')
                        .querySelector('.story-menu-trigger')
                        .setAttribute('aria-expanded', 'false');
                }
            } catch (err) {
                console.error('[CopyLink] Ошибка:', err);
                alert('Не удалось скопировать ссылку');
            }
        });
    });
})();

/**
 * Scroll Enhancements
 * 
 * 1. Кнопка "Наверх" — появляется после скролла вниз, плавно возвращает наверх
 * 2. Индикатор прогресса чтения — тонкая полоска вверху, показывает сколько прочитано
 * 
 * Принципы:
 * - Работает на всех страницах (кнопка) и только на статьях (прогресс)
 * - Уважает prefers-reduced-motion (доступность)
 * - Не блокирует основной поток (requestAnimationFrame)
 */
(function() {
    'use strict';

    const CONFIG = {
        SHOW_BUTTON_AFTER: 400,
        SCROLL_DURATION: 500,
        THROTTLE_MS: 16,
    };

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function throttle(fn, wait) {
        let lastTime = 0;
        return function(...args) {
            const now = Date.now();
            if (now - lastTime >= wait) {
                lastTime = now;
                fn.apply(this, args);
            }
        };
    }

    function initScrollToTopButton() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'scroll-to-top';
        button.setAttribute('aria-label', 'Наверх');
        button.title = 'Вернуться наверх';
        button.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" 
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" 
                 stroke-linejoin="round" aria-hidden="true">
                <path d="M18 15l-6-6-6 6"/>
            </svg>
        `;
        document.body.appendChild(button);

        const toggleVisibility = throttle(() => {
            const scrolled = window.pageYOffset || document.documentElement.scrollTop;
            button.classList.toggle('is-visible', scrolled > CONFIG.SHOW_BUTTON_AFTER);
        }, CONFIG.THROTTLE_MS);

        button.addEventListener('click', () => {
            if (prefersReducedMotion) {
                window.scrollTo(0, 0);
                return;
            }

            const start = window.pageYOffset;
            const startTime = performance.now();

            function animateScroll(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / CONFIG.SCROLL_DURATION, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                
                window.scrollTo(0, start * (1 - eased));
                
                if (progress < 1) {
                    requestAnimationFrame(animateScroll);
                }
            }

            requestAnimationFrame(animateScroll);
        });

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        toggleVisibility();
    }

    function initReadingProgressBar() {
        const article = document.querySelector('article[data-story-id]');
        if (!article) return;

        const container = document.createElement('div');
        container.className = 'reading-progress';
        container.setAttribute('role', 'progressbar');
        container.setAttribute('aria-label', 'Прогресс чтения');
        container.setAttribute('aria-valuemin', '0');
        container.setAttribute('aria-valuemax', '100');

        const bar = document.createElement('div');
        bar.className = 'reading-progress__bar';
        container.appendChild(bar);

        document.body.insertBefore(container, document.body.firstChild);

        const updateProgress = throttle(() => {
            const articleRect = article.getBoundingClientRect();
            const articleTop = articleRect.top + window.pageYOffset;
            const articleHeight = article.offsetHeight;
            const viewportHeight = window.innerHeight;
            const scrolled = window.pageYOffset;

            const start = articleTop;
            const end = articleTop + articleHeight - viewportHeight;
            const total = end - start;

            let progress = 0;
            if (total > 0) {
                progress = Math.max(0, Math.min(100, ((scrolled - start) / total) * 100));
            } else {
                progress = scrolled > articleTop ? 100 : 0;
            }

            bar.style.width = progress + '%';
            container.setAttribute('aria-valuenow', Math.round(progress).toString());
        }, CONFIG.THROTTLE_MS);

        window.addEventListener('scroll', updateProgress, { passive: true });
        window.addEventListener('resize', updateProgress, { passive: true });
        updateProgress();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initScrollToTopButton();
        initReadingProgressBar();
    });
})();

/**
 * Icon Buttons: универсальный AJAX для всех иконочных кнопок
 * 
 * Обрабатывает формы с кнопками .icon-btn:
 * - Закладки
 * - Подписки на комментарии
 * - Лайки (если будут)
 * 
 * Особенности:
 * - Переключает класс .is-active
 * - Обновляет title для accessibility
 * - Добавляет анимацию "pop"
 * - При ошибке показывает уведомление вместо form.submit()
 */
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        const button = form.querySelector('.icon-btn');
        
        if (!button) return;
        
        e.preventDefault();
        
        const isActive = button.classList.contains('is-active');
        button.disabled = true;
        
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (response.ok) {
                const newState = !isActive;
                button.classList.toggle('is-active', newState);
                
                // Обновляем title для accessibility
                const title = button.getAttribute('title') || '';
                if (title.includes('Убрать') || title.includes('Отписаться')) {
                    button.setAttribute('title', title
                        .replace('Убрать из закладок', 'Сохранить в закладки')
                        .replace('Отписаться от комментариев', 'Подписаться на комментарии'));
                } else {
                    button.setAttribute('title', title
                        .replace('Сохранить в закладки', 'Убрать из закладок')
                        .replace('Подписаться на комментарии', 'Отписаться от комментариев'));
                }
                
                // Анимация
                button.classList.add('just-toggled');
                setTimeout(() => button.classList.remove('just-toggled'), 300);
                
                console.log(`[IconBtn] ${form.action}: ${newState ? 'activated' : 'deactivated'}`);
            } else {
                console.error(`[IconBtn] Server error: ${response.status}`);
                alert('Ошибка сервера. Попробуйте позже.');
            }
        } catch (error) {
            console.error('[IconBtn] Network error:', error);
            alert('Ошибка сети. Проверьте подключение.');
        } finally {
            button.disabled = false;
        }
    });
});

/**
 * Автоматическое скрытие flash-сообщений через 5 секунд
 */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert.is-success, .alert.is-notice');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

/**
 * Подсветка новых комментариев при переходе по якорю
 */
document.addEventListener('DOMContentLoaded', function() {
    // Если в URL есть хэш #comment-123
    if (window.location.hash && window.location.hash.startsWith('#comment-')) {
        const commentId = window.location.hash.substring(1);
        const comment = document.getElementById(commentId);
        
        if (comment) {
            // Прокрутка к комментарию
            comment.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Подсветка на 3 секунды
            comment.classList.add('comment-highlighted');
            setTimeout(() => {
                comment.classList.remove('comment-highlighted');
            }, 3000);
        }
    }
});