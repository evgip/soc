class SuggestionsManager {
    constructor() {
        // 1. Пытаемся найти модалку
        this.modal = document.getElementById('suggest-modal');
        
        // 2. ЗАЩИТНАЯ ПРОВЕРКА: Если модалки нет на этой странице, прекращаем выполнение.
        // Это предотвратит ошибку и не будет нагружать браузер на других страницах.
        if (!this.modal) {
            return; 
        }
        
        // 3. Если модалка есть, инициализируем остальные элементы
        this.tagsGroup = document.getElementById('suggest-tags-group');
        this.textGroup = document.getElementById('suggest-text-group');
        this.form = document.getElementById('suggest-form');
        
        this.init();
    }
    
    init() {
        // Открытие модалки (используем closest для надежности)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.suggest-edit-btn');
            if (btn) {
                this.openSuggestModal(btn);
            }
        });
        
        // Закрытие модалки при клике на затемненный фон (backdrop)
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.modal.close();
            }
        });

        // Закрытие модалки кнопкой "Отмена"
        const cancelBtn = this.modal.querySelector('.close-modal-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.modal.close());
        }
        
        // Отправка формы
        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitSuggestion();
            });
        }
    }
    
    openSuggestModal(button) {
        const targetType = button.dataset.targetType;
        const targetId = button.dataset.targetId;
        const currentTitle = button.dataset.currentTitle || '';
        
        document.getElementById('suggest-target-type').value = targetType;
        document.getElementById('suggest-target-id').value = targetId;
        document.getElementById('suggest-title').value = currentTitle;
        
        if (targetType === 'Story') {
            this.tagsGroup.classList.remove('hidden');
            this.textGroup.classList.add('hidden');
        } else if (targetType === 'Comment') {
            this.tagsGroup.classList.add('hidden');
            this.textGroup.classList.remove('hidden');
        }
        
        this.modal.showModal();
    }
    
    async submitSuggestion() {
        const formData = new FormData(this.form);
        const targetType = formData.get('target_type');
        const proposedData = {};
        
        const newTitle = formData.get('title')?.trim();
        if (newTitle) {
            proposedData.title = newTitle;
        }
        
        if (targetType === 'Story') {
            const selectedTags = Array.from(this.form.querySelectorAll('input[name="tags[]"]:checked'))
                .map(cb => parseInt(cb.value));
            proposedData.tag_ids = selectedTags;
        } else if (targetType === 'Comment') {
            const text = formData.get('text')?.trim();
            if (text) {
                proposedData.text = text;
            }
        }
        
        if (Object.keys(proposedData).length === 0) {
            alert('Укажите хотя бы одно изменение');
            return;
        }
        
        formData.append('proposed_data', JSON.stringify(proposedData));
        
        try {
            const response = await fetch('/suggestions', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.modal.close();
                window.location.reload();
            } else {
                alert('Ошибка: ' + result.error);
            }
        } catch (error) {
            alert('Ошибка сети: ' + error.message);
        }
    }
}

// Инициализация только после полной загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    new SuggestionsManager();
});