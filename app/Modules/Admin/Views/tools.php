<h1>🛠️ Инструменты разработчика фреймворка</h1>

<p class="hint">
    Управление состоянием, оптимизацией и логами ядра без необходимости использовать терминал CLI или сторонние утилиты.
</p>

<hr>

<!-- БЛОК 1: ASSET PIPELINE (CSS + JS) -->
<div class="form-field-group">
    <h3>⚡ Компиляция ресурсов (Asset Pipeline)</h3>
    <p class="hint">
        Автоматический рекурсивный поиск файлов <code>.css</code> и <code>.js</code> внутри всех папок <code>app/Modules/</code>. 
        PHP склеит их, очистит от комментариев/пробелов и перезапишет финальные продакшен-бандлы в <code>public/assets/css/app.min.css</code> и <code>public/assets/js/app.min.js</code>.
    </p>
    
    <form action="<?= route('admin.tools.compile_assets') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn-primary">Скомпилировать ассеты (CSS + JS)</button>
    </form>
</div>

<hr>

<!-- БЛОК 2: ОЧИСТКА ФАЙЛОВЫХ ЛОГОВ -->
<div class="form-field-group">
    <h3>📂 Файловая система: Очистка storage/logs/</h3>
    <p class="hint">
        Мгновенное обнуление содержимого файлов логов разработки <code>app.log</code> (ошибки PHP) и резервного лога безопасности <code>audit.log</code>, расположенных в изолированном верхнем разделе вашего проекта. Сами файлы и их права доступа сохраняются.
    </p>
    
    <form action="<?= route('admin.tools.clear_file_logs') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="button restore-link" data-confirm="Вы уверены, что хотите полностью очистить файлы логов на диске?">
            🗑️ Очистить логи на диске
        </button>
    </form>
</div>

<hr>

<!-- БЛОК 3: ОЧИСТКА ЛОГОВ В БАЗЕ ДАННЫХ -->
<div class="form-field-group">
    <h3>⚠️ База данных: Очистка таблицы audit_logs</h3>
    <p class="hint">
        Выполняет SQL-команду <code>TRUNCATE TABLE</code>, полностью и безвозвратно удаляя все накопленные записи аудита действий пользователей из СУБД MySQL. Операция также сбрасывает счетчик автоинкремента ID на 1.
    </p>
    
    <form action="<?= route('admin.tools.clear_db_audit') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="button restore-link" data-confirm="ВНИМАНИЕ! Вы уверены, что хотите БЕЗВОЗВРАТНО удалить все логи аудита из Базы Данных?">
            🚨 Очистить аудит в БД
        </button>
    </form>
</div>

<hr>

<!-- БЛОК 4: ОПТИМИЗАЦИЯ ЯДРА -->
<div class="form-field-group">
    <h3>🔀 Оптимизация ядра: Кэширование маршрутизатора</h3>
    <p class="hint">
        Склеивает и компилирует конфигурационные карты роутов всех изолированных модулей в единый плоский PHP-массив внутри <code>storage/cache/</code>. 
        Это убирает необходимость сканировать жесткий диск при каждом посещении сайта пользователями, кратно ускоряя генерацию страниц.
    </p>
    
    <div class="form-actions">
        <form action="<?= route('admin.tools.cache_routes') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn-primary">🚀 Включить кэш роутов</button>
        </form>
        
        <form action="<?= route('admin.tools.clear_cache_routes') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="button restore-link" data-confirm="Вы уверены, что хотите сбросить кэш маршрутов?">
                🗑️ Сбросить кэш
            </button>
        </form>
    </div>
</div>

<hr>

<!-- БЛОК 5: ТЕСТОВОЕ ПИСЬМО -->
<div class="form-field-group">
    <h3>📩 Отправка тестового письма</h3>
    <p class="hint">
        Отправляет тестовое сообщение на указанный email-адрес для проверки корректности настройки почтового сервера.
    </p>
    
    <form action="<?= route('admin.tools.send_test_email') ?>" method="POST">
        <?= csrf_field() ?>
        
        <div class="form-field-group">
            <label for="test_email">Email адрес получателя:</label>
            <input type="email" id="test_email" name="email" required class="form-input-wide" placeholder="example@domain.com">
        </div>
        
        <button type="submit" class="btn-primary">📩 Отправить тестовое письмо</button>
    </form>
</div>

<!-- БЛОК 6: ПЕРЕСЧЕТ CONFIDENCE SCORE -->
<div class="form-field-group">
    <h3>📊 Пересчет confidence_score (формула Вильсона)</h3>
    <p class="hint">
        Пересчитывает поле <code>confidence_score</code> для всех комментариев в базе данных по формуле Вильсона.
        Обработка идет пакетами по 1000 записей для предотвращения таймаутов.
    </p>
    
    <div id="reading-progress">
        <div class="progress-bar">
            <div id="reading-progress__bar" class="confidence-progress-bar">
                <span id="confidence-progress-text">0%</span>
            </div>
        </div>
        <p id="confidence-status"></p>
    </div>
    
    <form id="recalculate-confidence-form" action="<?= route('admin.tools.recalculate_confidence_score') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn-primary" id="confidence-submit-btn">
            🔄 Пересчитать confidence_score
        </button>
    </form>
</div>

<script nonce="<?= csp_nonce(); ?>">
document.getElementById('recalculate-confidence-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = document.getElementById('confidence-submit-btn');
    const progressDiv = document.getElementById('reading-progress');
    const progressBar = document.getElementById('reading-progress__bar');
    const progressText = document.getElementById('confidence-progress-text');
    const statusText = document.getElementById('confidence-status');
    
    // Блокируем кнопку
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Обработка...';
    progressDiv.style.display = 'block';
    
    let offset = 0;
    let totalProcessed = 0;
    let total = 0;
    
    try {
        while (true) {
            statusText.textContent = `Обработано ${totalProcessed} из ${total > 0 ? total : '...'}...`;
            
            // Читаем СВЕЖИЙ токен перед каждым запросом
            // (middleware ротирует токен при каждом POST)
            const csrfToken = getCsrfToken();
            
            const formData = new FormData();
            formData.append('offset', offset);
            formData.append('csrf_token', csrfToken);
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
                // ✅ headers (X-XSRF-TOKEN, X-Requested-With) добавляются автоматически перехватчиком из core_utils.js
            });
            
            // Проверяем статус ответа
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server error:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Получаем текст ответа для отладки
            const responseText = await response.text();
            console.log('Response:', responseText);
            
            // Пытаемся распарсить JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Сервер вернул не JSON. Проверьте консоль браузера.');
            }
            
            if (!result.success) {
                throw new Error(result.error || 'Неизвестная ошибка');
            }
            
            totalProcessed += result.processed;
            total = result.total;
            offset = result.nextOffset;
            
            // Обновляем прогресс
            const percentage = total > 0 ? Math.round((totalProcessed / total) * 100) : 0;
            progressBar.style.width = percentage + '%';
            progressText.textContent = percentage + '%';
            statusText.textContent = `Обработано ${totalProcessed} из ${total}...`;
            
            if (!result.hasMore) {
                break;
            }
            
            // Небольшая задержка между пакетами
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        statusText.innerHTML = '<span>✅ Пересчет завершен успешно!</span>';
        submitBtn.textContent = '✅ Готово';
        
    } catch (error) {
        console.error('Error:', error);
        statusText.innerHTML = '<span color="red">❌ Ошибка: ' + error.message + '</span>';
        submitBtn.disabled = false;
        submitBtn.textContent = '🔄 Повторить';
    }
});
</script>