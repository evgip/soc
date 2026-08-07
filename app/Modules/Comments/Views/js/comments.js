/**
 * Интерактивные функции комментариев
 * - Ответ на комментарий (Reply)
 * - Редактирование комментария (Edit)
 * - Сворачивание веток
 * - Защита от двойной отправки форм
 */

// Константы на верхнем уровне — доступны везде
const STORAGE_KEY = 'w3a_collapsed_comments';

document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // ЗАЩИТА ОТ ДВОЙНОЙ ОТПРАВКИ ФОРМ
    // ============================================
    document.addEventListener('submit', function(event) {
        const form = event.target;

        if (form.action && form.action.indexOf('/vote/') !== -1) {
            return true;
        }

        if (form.dataset.isSubmitting === 'true') {
            event.preventDefault();
            return false;
        }

        if (form.classList.contains('js-comment-delete-form')) {
            const confirmed = confirm('Вы уверены, что хотите удалить этот комментарий?');
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }

        form.dataset.isSubmitting = 'true';

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    });

    // ============================================
    // СЕЛЕКТОРЫ ЭЛЕМЕНТОВ
    // ============================================
    const replyButtons = document.querySelectorAll('.comment-reply-link');
    const editButtons = document.querySelectorAll('.comment-edit-trigger');
    const parentIdInput = document.getElementById('form-parent-id');
    const cancelBtn = document.getElementById('btn-cancel-reply');
    const commentForm = document.getElementById('main-comment-form');
    const textarea = document.getElementById('form-comment-textarea');
    const formContainer = document.getElementById('comment-form-container');

    // ============================================
    // 1. ОТВЕТ НА КОММЕНТАРИЙ (REPLY)
    // ============================================
    if (commentForm && replyButtons.length > 0) {
        replyButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                document.querySelectorAll('.comment-dynamic-edit-form').forEach(f => f.remove());
                document.querySelectorAll('.comment_text').forEach(t => t.style.display = 'block');

                const commentId = this.getAttribute('href').replace('#reply-to-', '');
                const parentComment = this.closest('li.comment');
                if (!parentComment) return;

                const authorLink = parentComment.querySelector('.comment_meta a');
                const authorName = authorLink ? authorLink.innerText : '';

                if (parentIdInput) parentIdInput.value = commentId;
                if (cancelBtn) cancelBtn.style.display = 'inline-block';

                parentComment.parentNode.insertBefore(commentForm, parentComment.nextSibling);
                if (textarea) textarea.focus();
            });
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (parentIdInput) parentIdInput.value = '';
                cancelBtn.style.display = 'none';
                if (formContainer) formContainer.appendChild(commentForm);
                if (textarea) textarea.value = '';
            });
        }
    }

    // ============================================
    // 2. ДИНАМИЧЕСКОЕ РЕДАКТИРОВАНИЕ (EDIT)
    // ============================================
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const commentId = this.getAttribute('data-id');
            const commentLi = this.closest('li.comment');
            if (!commentLi) return;

            const textBlock = document.getElementById(`comment-text-content-${commentId}`);
            if (!textBlock) return;

            if (commentLi.querySelector('.comment-dynamic-edit-form')) return;

            textBlock.style.display = 'none';
            const currentText = textBlock.getAttribute('data-raw') || '';
            const csrfToken = window.getCsrfToken ? window.getCsrfToken() : '';

            const editForm = document.createElement('form');
            editForm.action = `/comments/${commentId}/edit`;
            editForm.method = 'POST';
            editForm.className = 'comment-dynamic-edit-form';

            editForm.innerHTML = `
                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                <textarea name="comment_text" required>${escapeHtml(currentText)}</textarea>
                <div class="comment_actions">
                    <button type="submit">Сохранить</button>
                    <span class="divider">|</span>
                    <button type="button" class="comment-edit-cancel is-link">Отмена</button>
                </div>
            `;

            textBlock.parentNode.insertBefore(editForm, textBlock.nextSibling);

            const editTextarea = editForm.querySelector('textarea');
            if (editTextarea) editTextarea.focus();

            editForm.querySelector('.comment-edit-cancel').addEventListener('click', function() {
                editForm.remove();
                textBlock.style.display = 'block';
            });

            editForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = editForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Сохранение...';

                const formData = new FormData(editForm);

                try {
                    const response = await fetch(editForm.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    if (response.status === 419) {
                        throw new Error('Сессия истекла. Обновите страницу.');
                    }

                    const data = await response.json();

                    if (data.success) {
                        textBlock.innerHTML = data.html;
                        textBlock.setAttribute('data-raw', data.raw);
                        editForm.remove();
                        textBlock.style.display = 'block';
                    } else {
                        alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Ошибка соединения с сервером: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        });
    });

    // ============================================
    // 3. ОБРАБОТКА ФОРМ С ПОДТВЕРЖДЕНИЕМ УДАЛЕНИЯ
    // ============================================
    document.querySelectorAll('.js-confirm-delete').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm-message') || 'Вы уверены?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // ============================================
    // 4. ЯКОРЬ #reply-to-{id} В URL
    // ============================================
    const hash = window.location.hash;
    if (hash && hash.startsWith('#reply-to-')) {
        const commentId = hash.replace('#reply-to-', '');
        const replyLink = document.querySelector(`.comment-reply-link[data-id="${commentId}"]`);
        if (replyLink) {
            setTimeout(() => replyLink.click(), 100);
        }
    }

    // ============================================
    // 5. СВОРАЧИВАНИЕ КОММЕНТАРИЕВ (collapse)
    // ============================================
    
    // Загружаем сохранённое состояние
    let collapsedIds = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

    // Применяем сохранённое состояние
    collapsedIds.forEach(id => {
        const thread = document.querySelector(`[data-comment-id="${id}"]`);
        if (thread) {
            thread.classList.add('collapsed');
            const toggle = thread.querySelector('.collapse-toggle');
            if (toggle) toggle.textContent = '[+]';

            const hiddenCount = thread.querySelectorAll('.comment-thread').length;
            if (hiddenCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'collapsed-count';
                badge.textContent = `(+${hiddenCount})`;
                thread.querySelector('.comment-header')?.appendChild(badge);
            }
        }
    });

    // Обработчик клика на сворачивание
    document.addEventListener('click', (e) => {
        if (!e.target.classList.contains('collapse-toggle')) return;

        const thread = e.target.closest('.comment-thread');
        if (!thread) return;

        const commentId = thread.dataset.commentId;
        const isCollapsed = thread.classList.toggle('collapsed');

        e.target.textContent = isCollapsed ? '[+]' : '[–]';

        if (isCollapsed) {
            const hiddenCount = thread.querySelectorAll('.comment-thread').length;
            if (hiddenCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'collapsed-count';
                badge.textContent = `(+${hiddenCount})`;
                thread.querySelector('.comment-header')?.appendChild(badge);
            }
        } else {
            const badge = thread.querySelector('.collapsed-count');
            if (badge) badge.remove();
        }

        // Сохраняем состояние
        if (isCollapsed) {
            if (!collapsedIds.includes(commentId)) {
                collapsedIds.push(commentId);
            }
        } else {
            collapsedIds = collapsedIds.filter(id => id !== commentId);
        }
        localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsedIds));
    });

    // Горячая клавиша: C для сворачивания
    document.addEventListener('keydown', (e) => {
        if (e.key === 'c' || e.key === 'C') {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            const hovered = document.querySelector('.comment-thread:hover');
            if (hovered) {
                const toggle = hovered.querySelector('.collapse-toggle');
                if (toggle) toggle.click();
            }
        }
    });

    // ============================================
    // 6. КНОПКА "СВЕРНУТЬ ВСЕ / РАЗВЕРНУТЬ ВСЕ"
    // ============================================
    const collapseAllBtn = document.getElementById('collapse-all-comments');
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            const threads = document.querySelectorAll('.comment-thread');
            const allCollapsed = Array.from(threads).every(t => t.classList.contains('collapsed'));

            threads.forEach(thread => {
                const toggle = thread.querySelector('.collapse-toggle');
                if (allCollapsed) {
                    // Развернуть все
                    thread.classList.remove('collapsed');
                    if (toggle) toggle.textContent = '[–]';
                    const badge = thread.querySelector('.collapsed-count');
                    if (badge) badge.remove();
                } else {
                    // Свернуть все
                    thread.classList.add('collapsed');
                    if (toggle) toggle.textContent = '[+]';

                    const hiddenCount = thread.querySelectorAll('.comment-thread').length;
                    if (hiddenCount > 0 && !thread.querySelector('.collapsed-count')) {
                        const badge = document.createElement('span');
                        badge.className = 'collapsed-count';
                        badge.textContent = `(+${hiddenCount})`;
                        thread.querySelector('.comment-header')?.appendChild(badge);
                    }
                }
            });

            // Обновляем localStorage
            if (allCollapsed) {
                localStorage.removeItem(STORAGE_KEY);
                collapsedIds = [];
            } else {
                const allIds = Array.from(threads).map(t => t.dataset.commentId);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(allIds));
                collapsedIds = [...allIds];
            }

            // Меняем текст кнопки
            collapseAllBtn.textContent = allCollapsed ? 'Свернуть все ветки' : 'Развернуть все ветки';
        });
    }
});

// ============================================
// ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}