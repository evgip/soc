/**
 * Обновляет счетчик уведомлений в шапке
 * Использует CSS-класс .is-visible вместо inline-стилей
 */
function updateHeaderNotificationCount() {
    const badge = document.getElementById('header-notification-badge');
    if (!badge) return;
    
    fetch('/api/notifications/count')
        .then(response => {
            // 401/403 — пользователь не залогинен, скрываем
            if (response.status === 401 || response.status === 403) {
                hideBadge(badge);
                return null;
            }
            
            if (response.status === 419) {
                console.warn('CSRF истёк');
                hideBadge(badge);
                return null;
            }
            
            if (!response.ok) {
                console.warn('Счетчик уведомлений: HTTP ' + response.status);
                hideBadge(badge);
                return null;
            }
            
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                hideBadge(badge);
                return null;
            }
            
            return response.json();
        })
        .then(data => {
            if (!data) return;
            
            if (data.count > 0) {
                showBadge(badge, data.count);
            } else {
                hideBadge(badge);
            }
        })
        .catch(error => {
            console.error('Ошибка получения счетчика уведомлений:', error);
            // При ошибке НЕ трогаем текущее состояние бейджа
            // — чтобы не моргал при проблемах с сетью
        });
}

/**
 * Показывает бейдж с указанным числом
 */
function showBadge(badge, count) {
    const displayText = count > 99 ? '99+' : String(count);
    
    // Обновляем только если текст изменился (чтобы не перезапускать анимацию)
    if (badge.textContent !== displayText) {
        badge.textContent = displayText;
    }
    
    // Добавляем класс — CSS сам покажет бейдж
    badge.classList.add('is-visible');
    
    // Обновляем aria-label для доступности
    badge.setAttribute('aria-label', `${count} непрочитанных уведомлений`);
}

/**
 * Скрывает бейдж
 */
function hideBadge(badge) {
    badge.classList.remove('is-visible');
    badge.removeAttribute('aria-label');
}

// Запускаем при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    updateHeaderNotificationCount();
    setInterval(updateHeaderNotificationCount, 60000);
    
    // Делегирование событий для кликов по уведомлениям
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.notification-link');
        if (!link) return;
        
        const notificationId = link.dataset.notificationId;
        if (!notificationId) return;
        
        e.preventDefault();
        const destinationUrl = link.href;
        
        fetch(`/notifications/${notificationId}/read`, {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.status === 419) {
                alert('Сессия истекла. Обновите страницу.');
                location.reload();
                return;
            }
            
            updateHeaderNotificationCount();
            
            const notificationItem = link.closest('.notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('notification-unread');
                notificationItem.classList.add('notification-read');
            }
            
            window.location.href = destinationUrl;
        })
        .catch(error => {
            console.error('Ошибка при отметке уведомления:', error);
            window.location.href = destinationUrl;
        });
    });
});

// Кнопка "Отметить все как прочитанные"
document.getElementById('mark-all-read-btn')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    fetch('/notifications/mark-all-read', {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(response => {
        if (response.status === 419) {
            alert('Сессия истекла. Обновите страницу.');
            location.reload();
            return;
        }
        
        if (response.ok) {
            location.reload();
        } else {
            alert('Ошибка при отметке уведомлений.');
        }
    })
    .catch(error => {
        console.error('Ошибка при отметке всех уведомлений:', error);
        alert('Ошибка соединения с сервером.');
    });
});