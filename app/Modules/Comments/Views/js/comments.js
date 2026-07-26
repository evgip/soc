/**
 * Интерактивные функции комментариев в стиле Lobsters
 * - Ответ на комментарий (Reply)
 * - Редактирование комментария (Edit)
 * - Защита от двойной отправки форм
 */
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // ЗАЩИТА ОТ ДВОЙНОЙ ОТПРАВКИ ФОРМ
    // ============================================
    document.addEventListener('submit', function(event) {
        const form = event.target;

        // Исключаем формы голосования (ими управляет voting.js)
        if (form.action && form.action.indexOf('/vote/') !== -1) {
            return true;
        }

        // Защита от повторной отправки
        if (form.dataset.isSubmitting === 'true') {
            event.preventDefault();
            return false;
        }

        // Подтверждение удаления комментария
        if (form.classList.contains('js-comment-delete-form')) {
            const confirmed = confirm('Вы уверены, что хотите удалить этот комментарий?');
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }

        // Блокируем форму на время отправки
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

                // Закрываем все открытые формы редактирования
                document.querySelectorAll('.comment-dynamic-edit-form').forEach(f => f.remove());
                document.querySelectorAll('.comment_text').forEach(t => t.style.display = 'block');

                // Извлекаем ID комментария из href (#reply-to-{id})
                const commentId = this.getAttribute('href').replace('#reply-to-', '');

                // Находим родительский комментарий (li.comment)
                const parentComment = this.closest('li.comment');
                if (!parentComment) return;

                // Извлекаем имя автора из comment_meta
                const authorLink = parentComment.querySelector('.comment_meta a');
                const authorName = authorLink ? authorLink.innerText : '';

                // Устанавливаем parent_id
                if (parentIdInput) {
                    parentIdInput.value = commentId;
                }

                // Показываем кнопку отмены
                if (cancelBtn) {
                    cancelBtn.style.display = 'inline-block';
                }

                // Перемещаем форму под комментарий
                parentComment.parentNode.insertBefore(commentForm, parentComment.nextSibling);

                // Фокус на textarea
                if (textarea) {
                    textarea.focus();
                }
            });
        });

        // Обработка кнопки "Отмена" для ответа
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                // Сбрасываем parent_id
                if (parentIdInput) {
                    parentIdInput.value = '';
                }

                // Скрываем кнопку отмены
                cancelBtn.style.display = 'none';

                // Возвращаем форму в исходный контейнер
                if (formContainer) {
                    formContainer.appendChild(commentForm);
                }

                // Очищаем textarea
                if (textarea) {
                    textarea.value = '';
                }
            });
        }
    }

	// ============================================
	// 2. ДИНАМИЧЕСКОЕ РЕДАКТИРОВАНИЕ КОММЕНТАРИЯ (EDIT)
	// ============================================
	editButtons.forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();

			const commentId = this.getAttribute('data-id');

			// Находим родительский комментарий (li.comment)
			const commentLi = this.closest('li.comment');
			if (!commentLi) return;

			// Находим блок текста комментария
			const textBlock = document.getElementById(`comment-text-content-${commentId}`);
			if (!textBlock) return;

			// Если форма редактирования уже открыта — выходим
			if (commentLi.querySelector('.comment-dynamic-edit-form')) return;

			// Скрываем текущий текст
			textBlock.style.display = 'none';

			// Извлекаем исходный Markdown из data-raw
			const currentText = textBlock.getAttribute('data-raw') || '';

			//  Берём CSRF-токен из cookie через глобальную функцию из core_utils.js
			const csrfToken = window.getCsrfToken ? window.getCsrfToken() : '';

			// Создаём динамическую форму редактирования
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

			// Вставляем форму после блока текста
			textBlock.parentNode.insertBefore(editForm, textBlock.nextSibling);

			// Фокус на textarea
			const editTextarea = editForm.querySelector('textarea');
			if (editTextarea) {
				editTextarea.focus();
			}

			// ✅ Обработка кнопки "Отмена"
			editForm.querySelector('.comment-edit-cancel').addEventListener('click', function() {
				editForm.remove();
				textBlock.style.display = 'block';
			});

			// ✅ ПЕРЕХВАТ SUBMIT — отправляем через AJAX
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
						// ✅ CSRF-токен добавляется автоматически перехватчиком из core_utils.js
					});

					if (response.status === 419) {
						throw new Error('Сессия истекла. Обновите страницу.');
					}

					const data = await response.json();

					if (data.success) {
						// ✅ Обновляем текст комментария
						textBlock.innerHTML = data.html;
						textBlock.setAttribute('data-raw', data.raw);

						// Удаляем форму и показываем текст
						editForm.remove();
						textBlock.style.display = 'block';
					} else {
						// Ошибка — показываем сообщение
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
    // ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ: Экранирование HTML
    // ============================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

	// Обработка форм с подтверждением удаления
		document.querySelectorAll('.js-confirm-delete').forEach(function(form) {
			form.addEventListener('submit', function(e) {
				const message = this.getAttribute('data-confirm-message') || 'Вы уверены?';
				if (!confirm(message)) {
					e.preventDefault();
					return false;
				}
			});
		});
		
		
    // Проверяем, есть ли якорь #reply-to-{id} в URL
    const hash = window.location.hash;
    if (hash && hash.startsWith('#reply-to-')) {
        const commentId = hash.replace('#reply-to-', '');
        
        // Находим кнопку "Ответить" для этого комментария и кликаем по ней
        const replyLink = document.querySelector(`.comment-reply-link[data-id="${commentId}"]`);
        if (replyLink) {
            // Небольшая задержка, чтобы страница успела прокрутиться к якорю
            setTimeout(() => {
                replyLink.click();
            }, 100);
        }
    }

});


document.addEventListener('DOMContentLoaded', () => {
    const STORAGE_KEY = 'w3a_collapsed_comments';
    
    // Загружаем сохранённое состояние
    let collapsedIds = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    
    // Применяем сохранённое состояние к текущей странице
    collapsedIds.forEach(id => {
        const thread = document.querySelector(`[data-comment-id="${id}"]`);
        if (thread) {
            thread.classList.add('collapsed');
            const toggle = thread.querySelector('.collapse-toggle');
            if (toggle) {
                toggle.textContent = '[+]';
            }
            
            // Добавляем счётчик скрытых
            const hiddenCount = thread.querySelectorAll('.comment-thread').length;
            if (hiddenCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'collapsed-count';
                badge.textContent = `(+${hiddenCount})`;
                thread.querySelector('.comment-header').appendChild(badge);
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
        
        // Обновляем иконку
        e.target.textContent = isCollapsed ? '[+]' : '[–]';
        
        // Считаем скрытые комментарии
        if (isCollapsed) {
            const hiddenCount = thread.querySelectorAll('.comment-thread').length;
            if (hiddenCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'collapsed-count';
                badge.textContent = `(+${hiddenCount})`;
                thread.querySelector('.comment-header').appendChild(badge);
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
    
    // Горячая клавиша: C для сворачивания комментария под курсором
    document.addEventListener('keydown', (e) => {
        if (e.key === 'c' || e.key === 'C') {
            // Проверяем, что не в текстовом поле
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            const hovered = document.querySelector('.comment-thread:hover');
            if (hovered) {
                const toggle = hovered.querySelector('.collapse-toggle');
                if (toggle) {
                    toggle.click();
                }
            }
        }
    });
});


// Кнопка "Свернуть все / Развернуть все"
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
                    thread.querySelector('.comment-header').appendChild(badge);
                }
            }
        });
        
        // Обновляем localStorage
        if (allCollapsed) {
            localStorage.removeItem(STORAGE_KEY);
        } else {
            const allIds = Array.from(threads).map(t => t.dataset.commentId);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(allIds));
        }
        
        // Меняем текст кнопки
        collapseAllBtn.textContent = allCollapsed ? 'Свернуть все ветки' : 'Развернуть все ветки';
    });
}