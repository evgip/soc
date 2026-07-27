<?php

use W3a\Core\Router;
use W3a\Core\Container;

/**
 * Получить сервис из контейнера или установить сам контейнер.
 * 
 * @param string|Container|null $abstract Имя сервиса или экземпляр Container для инициализации
 * @return mixed
 */
if (!function_exists('container')) {
    function container(string|Container|null $abstract = null): mixed
    {
        // Статическая переменная хранит экземпляр контейнера между вызовами функции
        static $containerInstance = null;

        // 1. Если передан экземпляр Container, мы его инициализируем (сохраняем)
        if ($abstract instanceof Container) {
            $containerInstance = $abstract;
            return $containerInstance;
        }

        // 2. Если контейнер ещё не инициализирован, выбрасываем ошибку
        if ($containerInstance === null) {
            throw new \RuntimeException('Application container not initialized. Call container($container) in Application::bootstrap() first.');
        }

        // 3. Если передана строка, возвращаем запрошенный сервис
        if (is_string($abstract)) {
            return $containerInstance->get($abstract);
        }

        // 4. Если ничего не передано, возвращаем сам контейнер
        return $containerInstance;
    }
}

/**
 * Универсальный хелпер для ленивой загрузки и кэширования экземпляров из DI-контейнера.
 */
if (!function_exists('get_cached_container')) {
    function get_cached_container(string $abstract, ?callable $fallback = null): mixed
    {
        static $cache = [];

        if (!array_key_exists($abstract, $cache)) {
            try {
                // Теперь это безопасно вызовет container($abstract)
                $cache[$abstract] = container($abstract);
            } catch (\Throwable $e) {
                error_log("get_cached_container failed for {$abstract}: " . $e->getMessage());
                $cache[$abstract] = $fallback !== null ? $fallback($e) : throw $e;
            }
        }

        return $cache[$abstract];
    }
}

/**
 * Генерация URL по имени маршрута
 * 
 * @param string $name Имя маршрута
 * @param array $params Параметры маршрута
 * @return string URL
 * 
 * Пример:
 *   route('profile', ['id' => 1])  // /user/1
 *   route('story.show', ['slug' => 'hello-world'])  // /story/hello-world
 */
if (!function_exists('route')) {
	function route(string $name, array $params = []): string
	{
		$router = get_cached_container(Router::class, fn() => null);
		if ($router !== null) {
			try {
				return $router->route($name, $params);
			} catch (Throwable $e) {
				error_log("Route helper failed: " . $e->getMessage());
			}
		}
		return '#route-error';
	}
}

/**
 * Выполнить HTTP редирект
 */
if (!function_exists('redirect')) {
	function redirect(string $url, int $code = 302): never
	{
		throw new \W3a\Core\Exceptions\RedirectException($url, $code);
	}
}

/**
 * Global helper to output localized translation strings
 * Глобальный хелпер для вывода локализованных строк перевода
 */
if (!function_exists('__')) {
	function __(string $key, array $replace = []): string
	{
		return \W3a\Core\Lang::get($key, $replace);
	}
}

/**
 * Подключение partial-шаблона с поддержкой тем (Fallback Chain).
 *
 * @param string $path Путь в формате 'Votes::_voters' или 'Users::_avatar'
 * @param array  $vars Переменные для передачи в шаблон
 * @throws \RuntimeException Если шаблон не найден ни в теме, ни в модуле
 * @throws \InvalidArgumentException Если формат пути неверный
 */
if (!function_exists('partial')) {
	function partial(string $path, array $vars = []): void
	{
		// 1. Разбор пути: "Votes::_voters" → $module = 'Votes', $file = '_voters'
		$parts = explode('::', $path);
		if (count($parts) !== 2) {
			throw new \InvalidArgumentException("Неверный формат пути partial. Используйте 'Модуль::файл', например: 'Votes::_voters'");
		}
		[$module, $file] = $parts;

		// 2. Получаем имя активной темы через глобальный контейнер
		// (Мы используем $GLOBALS, так как находимся в глобальной функции)
		$theme = 'default';
		if (isset($GLOBALS['app_container']) && $GLOBALS['app_container']->has(\W3a\Core\Config::class)) {
			$theme = $GLOBALS['app_container']->get(\W3a\Core\Config::class)->get('config.app.theme', 'default');
		}

		// 3. Цепочка поиска (Fallback Chain)
		// ВНИМАНИЕ: Проверьте, что путь dirname(__DIR__) . '/app/Modules' соответствует вашей реальной структуре!
		$appModulesPath = dirname(__DIR__) . '/app/Modules';
		$themesPath = dirname(__DIR__, 2) . '/themes';

		$candidates = [
			// Приоритет 1: Переопределение в активной теме для конкретного модуля
			"{$themesPath}/{$theme}/Modules/{$module}/Views/{$file}.php",

			// Приоритет 2: Глобальное переопределение в теме (например, для общих элементов)
			"{$themesPath}/{$theme}/Views/{$file}.php",

			// Приоритет 3: Оригинальный файл модуля (ваш исходный путь)
			"{$appModulesPath}/{$module}/Views/{$file}.php",
		];

		$filePath = null;
		foreach ($candidates as $candidate) {
			if (file_exists($candidate)) {
				$filePath = $candidate;
				break; // Нашли первый существующий файл, прекращаем поиск
			}
		}

		if ($filePath === null) {
			throw new \RuntimeException(
				"Partial не найден: '{$path}'. " .
					"Проверьте пути в теме '{$theme}' или в модуле '{$module}'."
			);
		}

		// 4. Безопасное извлечение переменных и включение файла
		// Используем замыкание (IIFE), чтобы переменные из $vars не "загрязняли" 
		// глобальную область видимости или область видимости вызывающего шаблона.
		// EXTR_SKIP гарантирует, что мы не перезапишем критичные системные переменные.
		(function () use ($filePath, $vars) {
			extract($vars, EXTR_SKIP);
			include $filePath;
		})();
	}
}

/**
 * HTML-escape a string (null-safe)
 * HTML-экранирование строки (безопасно при null)
 */
if (!function_exists('e')) {
	function e(?string $value): string
	{
		return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
	}
}

/**
 * Format a datetime string (nullable input)
 * Форматирование даты и времени (допускается null)
 */
if (!function_exists('dt')) {
	function dt(?string $datetime, string $format = 'd.m.Y H:i'): string
	{
		if (!$datetime) return '';
		return date($format, strtotime($datetime));
	}
}

/**
 * Склонение существительных для русского языка
 * 
 * @param int $n Число для склонения
 * @param array $forms Массив из 3 форм: [1 форма, 2-4 форма, 5+ форма]
 *                     Пример: ['комментарий', 'комментария', 'комментариев']
 * @return string Правильная форма слова
 * @throws InvalidArgumentException Если массив содержит менее 3 элементов
 */
if (!function_exists('plural')) {
	function plural(int $n, array $forms): string
	{
		// Валидация входных данных
		if (count($forms) < 3) {
			throw new InvalidArgumentException(
				'Forms array must contain exactly 3 elements for Russian pluralization: ' .
					'[singular, paucal, plural]. Example: ["комментарий", "комментария", "комментариев"]'
			);
		}

		// Приведение к абсолютному значению и получение последних двух цифр
		$n = abs($n) % 100;
		$n1 = $n % 10;

		// Исключения: 11-14 всегда используют форму "5+"
		if ($n > 10 && $n < 20) {
			return $forms[2];
		}

		// Форма для 2-4 (два комментария, три комментария)
		if ($n1 > 1 && $n1 < 5) {
			return $forms[1];
		}

		// Форма для 1 (один комментарий)
		if ($n1 === 1) {
			return $forms[0];
		}

		// Форма для 0, 5-9, 20+ (ноль комментариев, пять комментариев)
		return $forms[2];
	}
}

/**
 * Generate a hidden CSRF token field
 * Генерация скрытого поля с CSRF-токеном
 */
if (!function_exists('csrf_field')) {
	function csrf_field(): string
	{
		try {
			// ✅ Получаем Request из контейнера
			$request = container(\W3a\Core\Request::class);
			return $request->csrfField();
		} catch (\Throwable $e) {
			// Fallback: если контейнер не инициализирован
			error_log("csrf_field() failed: " . $e->getMessage());

			// Создаём временный Request
			$request = new \W3a\Core\Request();
			return $request->csrfField();
		}
	}
}

/**
 * Получить nonce для CSP (для inline скриптов и стилей)
 * 
 * Использование:
 *   <script nonce="<?= csp_nonce() ?>">...</script>
 *   <style nonce="<?= csp_nonce() ?>">...</style>
 * 
 * @return string Nonce для CSP
 */
if (!function_exists('csp_nonce')) {
	function csp_nonce(): string
	{
		static $nonce = null;

		if ($nonce === null) {
			$security = get_cached_container(W3a\Core\Security::class, fn() => null);
			if ($security !== null) {
				try {
					$nonce = $security->getNonce();
				} catch (Throwable $e) {
					$nonce = bin2hex(random_bytes(16));
				}
			} else {
				$nonce = bin2hex(random_bytes(16));
			}
		}

		return $nonce;
	}
}

/**
 * Render flash messages by type (success, error, notice)
 * Вывод flash-сообщений по типу (успех, ошибка, информационное)
 */
if (!function_exists('render_flashes')) {
	function render_flashes(): void
	{
		$types = [
			'success' => 'is-success',
			'error'   => 'is-danger',
			'notice'  => 'is-notice'
		];

		if (session_status() === PHP_SESSION_NONE) {
			@session_start();
		}

		foreach ($types as $key => $class) {
			if (isset($_SESSION['flash'][$key])) {
				$message = htmlspecialchars($_SESSION['flash'][$key]);
				unset($_SESSION['flash'][$key]); // Удаляем после получения

				$title = $key === 'is-success' ? 'Успех' : ($key === 'error' ? 'Ошибка' : 'Информация');

				echo '<div class="alert ' . $class . '">';
				echo '<strong>' . $title . '!</strong> ' . $message;
				echo '</div>';
			}
		}
	}
}

/**
 * Получить значение из конфигурации
 * 
 * @param string|null $key Ключ конфигурации
 * @param mixed $default Значение по умолчанию
 * @return mixed
 */
if (!function_exists('config')) {
	function config(?string $key = null, mixed $default = null): mixed
	{
		static $config = null;

		if ($config === null) {
			$config = get_cached_container(W3a\Core\Config::class, fn() => null);
		}

		if ($config === null) {
			return $default;
		}

		if ($key === null) {
			return $config;
		}

		return $config->get($key, $default);
	}
}

/**
 * Retrieve application name
 * Получение названия приложения
 */
if (!function_exists('app_name')) {
	function app_name(): string
	{
		return config('config.app.name', 'w3a');
	}
}

/**
 * Generate URL for a specific comment with anchor
 * Генерация URL для конкретного комментария с якорем
 *
 * @param int $storyId    - Story ID
 *                        - ID истории
 * @param int $commentId  - Comment ID
 *                        - ID комментария
 * @return string URL with fragment identifier (e.g., `/story/123#comment-block-456`)
 *                URL с якорем (например, `/story/123#comment-block-456`)
 */
if (!function_exists('comment_url')) {
	function comment_url(int $storyId, int $commentId): string
	{
		$baseUrl = "/story/{$storyId}";
		$anchor = "comment-block-{$commentId}";  // ← Updated here

		return "{$baseUrl}#{$anchor}";
	}
}

/**
 * Validate URL (scheme, format, length, safe characters)
 * Валидация URL (схема, формат, длина, безопасные символы)
 */
if (!function_exists('isValidUrl')) {
	function isValidUrl(string $url): bool
	{
		// Basic format validation
		// Базовая валидация формата
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}

		$parsed = parse_url($url);

		// Scheme check
		// Проверка схемы
		$allowedSchemes = ['http', 'https'];
		if (!in_array($parsed['scheme'] ?? '', $allowedSchemes, true)) {
			return false;
		}

		// Block control characters
		// Блокировка управляющих символов
		if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
			return false;
		}

		// Length check (DoS protection)
		// Проверка длины (защита от DoS)
		if (strlen($url) > 2048) {
			return false;
		}

		return true;
	}
}

/**
 * Truncate HTML to plain text with ellipsis (length ~300 chars)
 * Обрезка HTML до обычного текста с многоточием (~300 символов)
 */
if (!function_exists('truncateDescription')) {
	function truncateDescription(string $html, int $length = 300): string
	{
		$text = strip_tags($html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if (mb_strlen($text) > $length) {
			$text = mb_substr($text, 0, $length);
			$text = preg_replace('/ [^ ]*$/u', '', $text);
			$text .= '…';
		}

		return $text;
	}
}

/**
 * Check if HTML description needs truncation (length > 300 chars)
 * Проверка, нужно ли обрезать описание (длина > 300 символов)
 */
if (!function_exists('needsTruncation')) {
	function needsTruncation(string $html, int $length = 300): bool
	{
		$text = strip_tags($html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return mb_strlen($text) > $length;
	}
}

/**
 * Sanitize HTML links: keep only <a> with clean href attribute
 * Очистка HTML-ссылок: оставить только <a> с безопасным href
 */
if (!function_exists('safeLink')) {
	function safeLink(?string $text): string
	{
		if ($text === null || $text === '') return '—';

		// Разрешённые протоколы
		$allowedProtocols = '/^(https?|mailto|tel):/i';

		return preg_replace_callback(
			'/<\/?a(\s+[^>]*)?>/i',
			function ($m) use ($allowedProtocols) {
				// Закрывающий тег
				if (strpos($m[0], '</a') === 0) {
					return '</a>';
				}

				// Открывающий тег
				if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $m[1], $href)) {
					$url = $href[1];

					// Проверка протокола
					if (!preg_match($allowedProtocols, $url)) {
						return '<a rel="noopener noreferrer">';
					}

					return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" rel="noopener noreferrer">';
				}

				return '<a rel="noopener noreferrer">';
			},
			$text
		);
	}
}

/**
 * Получить значение из переменных окружения (.env)
 *
 * @param string $key Ключ
 * @param mixed $default Значение по умолчанию
 * @return mixed
 */
if (!function_exists('env')) {
	function env(string $key, mixed $default = null): mixed
	{
		return \W3a\Core\Env::get($key, $default);
	}
}

/**
 * Получить экземпляр Markdown парсера
 * 
 * @return \App\Modules\Content\Core\Markdown
 */
if (!function_exists('markdown_instance')) {
	function markdown_instance(): \App\Modules\Content\Core\Markdown
	{
		return get_cached_container(App\Modules\Content\Core\Markdown::class);
	}
}

/**
 * Парсинг Markdown в HTML (полный режим - для постов/историй)
 * 
 * @param string|null $text Markdown текст
 * @param bool $allowImages Разрешить изображения (по умолчанию true)
 * @return string HTML
 * 
 * Пример:
 *   echo markdown('# Привет **мир**');
 *   // <h1>Привет <strong>мир</strong></h1>
 */
if (!function_exists('markdown')) {
	function markdown(?string $text, bool $allowImages = true): string
	{
		return markdown_instance()->parse($text, $allowImages);
	}
}

/**
 * Парсинг Markdown для комментариев (ограниченный режим - без картинок)
 * 
 * @param string|null $text Markdown текст
 * @return string HTML
 * 
 * Пример:
 *   echo markdown_comment('Отличный пост! ![img](http://...)');
 *   // <p>Отличный пост! ![img](http://...)</p>  ← картинка НЕ отобразится
 */
if (!function_exists('markdown_comment')) {
	function markdown_comment(?string $text): string
	{
		return markdown_instance()->parseComment($text);
	}
}

/**
 * Парсинг простого текста (без Markdown, только экранирование и переносы строк)
 * 
 * @param string|null $text Обычный текст
 * @return string HTML
 * 
 * Пример:
 *   echo markdown_plain("Привет\nмир!");
 *   // <p>Привет<br />мир!</p>
 */
if (!function_exists('markdown_plain')) {
	function markdown_plain(?string $text): string
	{
		return markdown_instance()->parsePlain($text);
	}
}

/**
 * Очистить кэш Markdown
 * 
 * Полезно при изменении настроек парсера или после массового обновления контента
 * 
 * @return void
 */
if (!function_exists('markdown_clear_cache')) {
	function markdown_clear_cache(): void
	{
		markdown_instance()->clearCache();
	}
}

/**
 * Получить HTML-код капчи
 * 
 * Использование в шаблонах:
 *   <?= captcha() ?>
 * 
 * @return string HTML капчи или пустая строка, если капча отключена
 */
if (!function_exists('captcha')) {

	function captcha(): string
	{
		static $html = null;

		if ($html === null) {
			$captcha = get_cached_container(App\Modules\Captcha\Core\Captcha::class, fn() => null);
			if ($captcha !== null) {
				try {
					$html = $captcha->getHtml();
				} catch (Throwable $e) {
					error_log("captcha getHtml failed: " . $e->getMessage());
					$html = '';
				}
			} else {
				$html = '';
			}
		}

		return $html;
	}
}

if (!function_exists('captcha_validate')) {

	function captcha_validate(?string $token = null): bool
	{
		try {
			$captcha = container(\App\Modules\Captcha\Core\Captcha::class);
			return $captcha->validate($token);
		} catch (\Throwable $e) {
			error_log("captcha_validate() failed: " . $e->getMessage());
			return false;
		}
	}
}

/**
 * Проверить, нужна ли капча текущему пользователю
 * 
 * @return bool
 */
if (!function_exists('captcha_is_required')) {
	function captcha_is_required(): bool
	{
		try {
			$captcha = container(\App\Modules\Captcha\Core\Captcha::class);
			return $captcha->isRequired();
		} catch (\Throwable $e) {
			return false;
		}
	}
}

/**
 * Рендерит HTML-код пагинации с умным диапазоном страниц.
 * 
 * Вместо вывода всех страниц подряд, функция показывает только страницы 
 * вокруг текущей, что критически важно для UX при большом количестве страниц.
 * Пример вывода при 100 страницах и текущей 50 (range = 2):
 * « Назад 1 ... 48 49 [50] 51 52 ... 100 Вперёд »
 * 
 * @param int $currentPage Текущая активная страница (начинается с 1)
 * @param int $totalPages Общее количество страниц
 * @param array $params Дополнительные GET-параметры для сохранения в ссылках 
 *                     (например, ['sort' => 'hot', 'search' => 'query'])
 * @param int $range Количество страниц для отображения слева и справа от текущей (по умолчанию 2)
 * @param string $baseUrl Базовый URL для ссылок. Если пустой, используются относительные ссылки (начинаются с '?').
 *                        Удобно использовать с функцией route(), например: route('admin.audit')
 * @return string HTML-разметка пагинации или пустая строка, если страниц <= 1
 * 
 * @example
 * // Простой случай (относительные ссылки)
 * echo pagination($currentPage, $totalPages);
 * 
 * // С сохранением фильтров и базовым URL из роутера
 * echo pagination($currentPage, $totalPages, ['sort' => 'new'], 2, route('stories.index'));
 */
if (!function_exists('pagination')) {
	function pagination(int $currentPage, int $totalPages, array $params = [], int $range = 2, string $baseUrl = ''): string
	{
		// =========================================================================
		// ШАГ 1: Ранний выход
		// =========================================================================
		// Если страница всего одна или меньше, пагинация не имеет смысла
		if ($totalPages <= 1) {
			return '';
		}

		$html = '<div class="pagination">';

		// =========================================================================
		// ШАГ 2: Кнопка "Назад"
		// =========================================================================
		if ($currentPage > 1) {
			$query = buildPaginationQuery($currentPage - 1, $params);
			// Если $baseUrl задан, склеиваем его с '?', иначе начинаем сразу с '?'
			$href = ($baseUrl ? rtrim($baseUrl, '/?&') : '') . '?' . $query;
			$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="pagination-btn pagination-prev">« Назад</a>';
		}

		// =========================================================================
		// ШАГ 3: Первая страница и многоточие (если текущая страница далеко от начала)
		// =========================================================================
		$start = max(1, $currentPage - $range);
		if ($start > 1) {
			$query = buildPaginationQuery(1, $params);
			$href = ($baseUrl ? rtrim($baseUrl, '/?&') : '') . '?' . $query;
			$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">1</a>';

			// Если разрыв больше 1 страницы, добавляем визуальный разделитель
			if ($start > 2) {
				$html .= '<span class="pagination-dots">...</span>';
			}
		}

		// =========================================================================
		// ШАГ 4: Основной диапазон страниц вокруг текущей
		// =========================================================================
		$end = min($totalPages, $currentPage + $range);
		for ($i = $start; $i <= $end; $i++) {
			if ($i === $currentPage) {
				// Текущая страница выделяется как неактивный элемент (span)
				$html .= '<span class="pagination-link current">' . $i . '</span>';
			} else {
				$query = buildPaginationQuery($i, $params);
				$href = ($baseUrl ? rtrim($baseUrl, '/?&') : '') . '?' . $query;
				$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">' . $i . '</a>';
			}
		}

		// =========================================================================
		// ШАГ 5: Последняя страница и многоточие (если текущая страница далеко от конца)
		// =========================================================================
		if ($end < $totalPages) {
			// Добавляем многоточие, если до конца больше 1 страницы
			if ($end < $totalPages - 1) {
				$html .= '<span class="pagination-dots">...</span>';
			}
			$query = buildPaginationQuery($totalPages, $params);
			$href = ($baseUrl ? rtrim($baseUrl, '/?&') : '') . '?' . $query;
			$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">' . $totalPages . '</a>';
		}

		// =========================================================================
		// ШАГ 6: Кнопка "Вперёд"
		// =========================================================================
		if ($currentPage < $totalPages) {
			$query = buildPaginationQuery($currentPage + 1, $params);
			$href = ($baseUrl ? rtrim($baseUrl, '/?&') : '') . '?' . $query;
			$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="pagination-btn pagination-next">Вперёд »</a>';
		}

		$html .= '</div>';

		return $html;
	}
}

/**
 * Формирует корректную строку запроса (query string) для URL пагинации.
 * 
 * Эта функция гарантирует, что все параметры будут правильно объединены,
 * а спецсимволы закодированы, избавляя от ручного склеивания строк через '&'.
 * 
 * @param int $page Целевой номер страницы для ссылки
 * @param array $additionalParams Дополнительные параметры, которые нужно добавить или перезаписать
 * @return string Готовая строка запроса БЕЗ начального знака '?'. 
 *                Примеры: "page=3" или "sort=hot&search=test&page=3"
 */
if (!function_exists('buildPaginationQuery')) {
	function buildPaginationQuery(int $page, array $additionalParams = []): string
	{
		// 1. Берем все текущие GET-параметры из глобального массива $_GET
		$params = $_GET;

		// 2. Удаляем старый параметр 'page', чтобы избежать дублирования 
		// (например, "?page=2&page=3")
		unset($params['page']);

		// 3. Объединяем с дополнительными параметрами. 
		// array_merge корректно перезапишет значения, если ключи совпадают
		$params = array_merge($params, $additionalParams);

		// 4. Добавляем целевой номер страницы, для которого строится ссылка
		$params['page'] = $page;

		// 5. http_build_query автоматически:
		//    - закодирует спецсимволы (например, пробелы в '+', кириллицу в %XX)
		//    - соединит пары ключ-значение через '&'
		//    - вернет чистую, безопасную строку
		return http_build_query($params);
	}
}
