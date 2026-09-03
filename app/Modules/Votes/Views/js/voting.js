(function() {
    'use strict';

    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!form.hasAttribute('data-clap-form')) return;
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;

        const wrapper = form.closest('.clappers');
        if (!wrapper) return;

        const btn = form.querySelector('.clap-btn');
        const type = form.getAttribute('data-type') || 'story';

        if (type === 'story') {
            const userClaps = parseInt(btn.dataset.userClaps || '0', 10);
            if (userClaps >= 50) return;
        }

        form.dataset.submitting = 'true';
        btn.disabled = true;

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => handleResponse(response))
        .then(data => {
            if (!data || data.status !== 'success') return;
            if (type === 'story') {
                updateClapUI(wrapper, data, btn);
            } else {
                updateLikeUI(wrapper, data, btn);
            }
        })
        .catch(error => {
            if (error.message !== 'CSRF_EXPIRED' && error.message !== 'REDIRECT') {
                console.warn('[Votes] Error:', error);
            }
        })
        .finally(() => {
            form.dataset.submitting = 'false';
            btn.disabled = false;
        });
    });

    async function handleResponse(response) {
        if (response.status === 419) {
            showNotice('Сессия истекла. Обновите страницу.', 'warning');
            setTimeout(() => location.reload(), 2000);
            throw new Error('CSRF_EXPIRED');
        }
        if (response.status === 401) {
            showNotice('Необходима авторизация.', 'info');
            setTimeout(() => {
                window.location.href = '/login?redirect=' + encodeURIComponent(location.pathname);
            }, 1500);
            throw new Error('REDIRECT');
        }
        if (response.status === 400 || response.status === 403) {
            const data = await response.json().catch(() => ({}));
            showNotice(data.message || 'Ошибка', 'error');
            throw new Error(data.message || 'Error');
        }
        if (response.status >= 500) {
            showNotice('Ошибка сервера. Попробуйте позже.', 'error');
            throw new Error('Server error');
        }
        if (!response.ok) {
            throw new Error('Unknown error');
        }
        return await response.json();
    }

    function updateClapUI(wrapper, data, btn) {
        var scoreEl = wrapper.querySelector('.clap-score');
        if (scoreEl && typeof data.new_score === 'number') {
            scoreEl.textContent = data.new_score;
            scoreEl.style.transition = 'transform 0.2s';
            scoreEl.style.transform = 'scale(1.4)';
            setTimeout(function() { scoreEl.style.transform = 'scale(1)'; }, 200);
        }
        if (typeof data.user_claps === 'number') {
            btn.dataset.userClaps = data.user_claps;
            if (data.user_claps >= 50) {
                btn.classList.add('clap-btn--maxed');
            }
            btn.classList.add('clap-btn--active');
        }
        btn.style.transform = 'scale(0.88)';
        btn.style.transition = 'transform 0.1s';
        setTimeout(function() { btn.style.transform = 'scale(1)'; }, 100);
        var ripple = document.createElement('span');
        ripple.className = 'clap-ripple';
        btn.appendChild(ripple);
        setTimeout(function() { ripple.remove(); }, 500);
        var allClappers = document.querySelectorAll('.clappers');
        var storyId = wrapper.querySelector('[data-clap-form]') ? wrapper.querySelector('[data-clap-form]').action.match(/\/clap\/(\d+)/) : null;
        if (storyId) {
            for (var i = 0; i < allClappers.length; i++) {
                var otherForm = allClappers[i].querySelector('[data-clap-form]');
                if (otherForm && otherForm !== wrapper.querySelector('[data-clap-form]') && otherForm.action.indexOf('/clap/' + storyId[1]) !== -1) {
                    var otherBtn = otherForm.querySelector('.clap-btn');
                    if (otherBtn) {
                        otherBtn.dataset.userClaps = data.user_claps;
                        if (data.user_claps >= 50) otherBtn.classList.add('clap-btn--maxed');
                        otherBtn.classList.add('clap-btn--active');
                    }
                    var otherScore = allClappers[i].querySelector('.clap-score');
                    if (otherScore && typeof data.new_score === 'number') {
                        otherScore.textContent = data.new_score;
                    }
                }
            }
        }
    }

    function updateLikeUI(wrapper, data, btn) {
        const scoreEl = wrapper.querySelector('.clap-score');
        if (scoreEl && typeof data.new_score === 'number') {
            scoreEl.textContent = data.new_score;
        }
        if (data.liked) {
            btn.classList.add('clap-btn--active');
        } else {
            btn.classList.remove('clap-btn--active');
        }
    }

    function showNotice(message, type) {
        if (typeof window.showFlashMessage === 'function') {
            window.showFlashMessage(message, type);
            return;
        }
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        alert(message);
    }
})();