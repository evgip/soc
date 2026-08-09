<?php

use W3a\Core\Http\Router;
use W3a\Core\Foundation\Container;
use App\Modules\Common\Support\Layout;



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


if (!function_exists('format_date_ru')) {
    /**
     * Форматирует дату в русском стиле: "7 июн", "15 авг"
     */
    function format_date_ru(string $datetime, string $format = 'short'): string
    {
        $months = [
            1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'авг',
            9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек'
        ];
        
        $timestamp = strtotime($datetime);
        $day = date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $year = date('Y', $timestamp);
        
        if ($format === 'short') {
            return $day . ' ' . $months[$month];
        }
        
        return $day . ' ' . $months[$month] . ' ' . $year;
    }
}


if (!function_exists('adaptive_time')) {
    /**
     * Показывает время в адаптивном формате: относительное для свежих, абсолютное для старых.
     * 
     * Логика отображения:
     * - < 1 часа: "5 минут назад"
     * - < 24 часов: "2 часа назад"
     * - Вчера: "Вчера"
     * - < 7 дней: "3 дня назад"
     * - В этом году: "13 июл"
     * - Прошлый год: "13 июл 2025"
     * 
     * @param string|null $datetime Дата и время
     * @return string HTML-элемент span с tooltip
     * 
     * @example
     * <?= adaptive_time($story['created_at']) ?>
     */
    function adaptive_time(?string $datetime): string
    {
        if (!$datetime) return '';
        
        $timestamp = strtotime($datetime);
        if (!$timestamp) return '';
        
        $now = time();
        $diff = $now - $timestamp;
        
        // Определяем формат отображения
        if ($diff < 60) {
            $display = 'только что';
        } elseif ($diff < 3600) {
            $minutes = (int)floor($diff / 60);
            $display = $minutes . ' ' . plural($minutes, ['минута', 'минуты', 'минут']) . ' назад';
        } elseif ($diff < 7200) {
            $display = '1 час назад';
        } elseif ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            $display = $hours . ' ' . plural($hours, ['час', 'часа', 'часов']) . ' назад';
        } elseif ($diff < 172800) { // 2 дня
            $display = 'Вчера';
        } elseif ($diff < 604800) { // 7 дней
            $days = (int)floor($diff / 86400);
            $display = $days . ' ' . plural($days, ['день', 'дня', 'дней']) . ' назад';
        } else {
            // Абсолютная дата для старых статей
            $months = [
                1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр',
                5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'авг',
                9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек'
            ];
            
            $day = date('j', $timestamp);
            $month = (int)date('n', $timestamp);
            $year = date('Y', $timestamp);
            $currentYear = date('Y', $now);
            
            // Если год текущий — без года
            if ($year == $currentYear) {
                $display = $day . ' ' . $months[$month];
            } else {
                // Если прошлый год — с годом
                $display = $day . ' ' . $months[$month] . ' ' . $year;
            }
        }
        
        // Полная дата для tooltip
        $title = date('d.m.Y H:i:s', $timestamp);
        
        return sprintf(
            '<span title="%s">%s</span>',
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
        );
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
				$html .= '<span class="pagination-link is-current">' . $i . '</span>';
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



if (!function_exists('render_editorjs_content')) {
    /**
     * Безопасно рендерит JSON от Editor.js в HTML.
     * 
     * @param string $json JSON от Editor.js
     * @param bool $skipFirstHeader Если true, пропускает первый блок H1/H2 (он уже выведен как title)
     */
    function render_editorjs_content(string $json, bool $skipFirstHeader = false): string 
    {
        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks']) || !is_array($data['blocks'])) {
            return '';
        }

        $inlineTags = '<b><strong><i><em><u><s><del><mark><code><a><br>';
        $html = '';
        $firstHeaderSkipped = false;
        
        foreach ($data['blocks'] as $block) {
            $type = $block['type'] ?? '';
            $d = $block['data'] ?? [];

            // Пропускаем первый заголовок, если нужно
            if ($skipFirstHeader && !$firstHeaderSkipped && $type === 'header') {
                $level = (int)($d['level'] ?? 2);
                if ($level <= 2) {
                    $firstHeaderSkipped = true;
                    continue; // Пропускаем этот блок
                }
            }

            switch ($type) {
                case 'header':
                    $level = min((int)($d['level'] ?? 2), 4);
                    $text = strip_tags($d['text'] ?? '', $inlineTags);
                    $html .= "<h{$level}>{$text}</h{$level}>\n";
                    break;

                case 'paragraph':
                    $text = strip_tags($d['text'] ?? '', $inlineTags);
                    $html .= "<p>{$text}</p>\n";
                    break;

                case 'quote':
                    $text = strip_tags($d['text'] ?? '', $inlineTags);
                    $caption = !empty($d['caption']) ? '<footer>' . e($d['caption']) . '</footer>' : '';
                    $html .= "<blockquote>{$text}{$caption}</blockquote>\n";
                    break;

                case 'list':
                    $tag = ($d['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
                    $html .= "<{$tag}>\n";
                    foreach ($d['items'] ?? [] as $item) {
                        $itemText = strip_tags($item['content'] ?? '', $inlineTags);
                        $html .= "<li>{$itemText}</li>\n";
                    }
                    $html .= "</{$tag}>\n";
                    break;

                case 'code':
                    $code = e($d['code'] ?? '');
                    $lang = e($d['language'] ?? 'plaintext');
                    $html .= "<pre><code class=\"language-{$lang}\">{$code}</code></pre>\n";
                    break;
                    
				case 'image':
					$url = filter_var(config('app.url') . ($d['file']['url'] ?? ''), FILTER_VALIDATE_URL);
					$caption = e($d['caption'] ?? '');

					if ($url) {
						$classes = ['editorjs-image'];
						
						if (!empty($d['withBorder'])) {
							$classes[] = 'editorjs-image--border';
						}
						if (!empty($d['withBackground'])) {
							$classes[] = 'editorjs-image--background';
						}
						if (!empty($d['stretched'])) {
							$classes[] = 'editorjs-image--stretched';
						}
						
						$classString = implode(' ', $classes);

						// Генерируем URL для полной версии (large)
						$parsedUrl = parse_url($url);
						$urlPath = $parsedUrl['path'] ?? '';
						$pathInfo = pathinfo($urlPath);
						$baseName = $pathInfo['filename'];
						$extension = $pathInfo['extension'] ?? 'webp';
						$dir = $pathInfo['dirname'] ?? '';
						
						$baseUrl = (isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '') 
								 . ($parsedUrl['host'] ?? '') 
								 . $dir . '/' . $baseName;
						
						// 🆕 URL полной версии для лайтбокса
						$fullUrl = $baseUrl . '_large.' . $extension;

						// Обёртка с кликом для лайтбокса
						$html .= "<figure class=\"{$classString}\" data-lightbox=\"true\">\n";
						$html .= "    <div class=\"lightbox-trigger\" data-full-src=\"{$fullUrl}\" data-caption=\"{$caption}\">\n";
						
						// Picture с обычной lazy-загрузкой
						$html .= "        <picture>\n";
						if (function_exists('imageavif')) {
							$html .= "            <source srcset=\"{$baseUrl}_large.avif, {$baseUrl}_medium.avif 800w, {$baseUrl}_small.avif 400w\" type=\"image/avif\">\n";
						}
						$html .= "            <source srcset=\"{$baseUrl}_large.webp, {$baseUrl}_medium.webp 800w, {$baseUrl}_small.webp 400w\" type=\"image/webp\">\n";
						$html .= "            <img src=\"{$url}\" alt=\"{$caption}\" loading=\"lazy\" decoding=\"async\">\n";
						$html .= "        </picture>\n";
						
						// 🆕 Иконка лупы (видна при hover)
						$html .= "        <button type=\"button\" class=\"lightbox-icon\" aria-label=\"Открыть в полном размере\">\n";
						$html .= "            <svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">\n";
						$html .= "                <circle cx=\"11\" cy=\"11\" r=\"8\"></circle>\n";
						$html .= "                <line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"></line>\n";
						$html .= "                <line x1=\"11\" y1=\"8\" x2=\"11\" y2=\"14\"></line>\n";
						$html .= "                <line x1=\"8\" y1=\"11\" x2=\"14\" y2=\"11\"></line>\n";
						$html .= "            </svg>\n";
						$html .= "        </button>\n";
						
						$html .= "    </div>\n";
						
						if ($caption) {
							$html .= "    <figcaption>{$caption}</figcaption>\n";
						}
						$html .= "</figure>\n";
					}
					break;

            }
        }
        
        return trim($html);
    }
}

if (!function_exists('render_story_with_paywall')) {
    /**
     * Рендерит статью с учётом paywall.
     * 
     * Если в JSON есть блок 'paywall' и у пользователя нет доступа —
     * обрезает контент на этом блоке и возвращает флаг для показа CTA.
     * 
     * @param array $story Массив статьи
     * @param bool $hasAccess Есть ли у читателя доступ к закрытой части
     * @return array ['html' => string, 'is_locked' => bool]
     */
    function render_story_with_paywall(array $story, bool $hasAccess): array
    {
        $json = $story['description_json'] ?? null;
        if (!$json) {
            return ['html' => '', 'is_locked' => false];
        }

        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks'])) {
            return ['html' => '', 'is_locked' => false];
        }

        $blocks = $data['blocks'];
        $paywallIndex = null;

        // Ищем первый блок paywall
        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? '') === 'paywall') {
                $paywallIndex = $index;
                break;
            }
        }

        // Paywall нет — рендерим как обычно
        if ($paywallIndex === null) {
            return [
                'html' => render_editorjs_content($json, true),
                'is_locked' => false,
            ];
        }

        // Есть доступ — рендерим всё (paywall-блок превращаем в разделитель)
        if ($hasAccess) {
            $html = render_editorjs_content($json, true);
            return ['html' => $html, 'is_locked' => false];
        }

        // Доступа нет — обрезаем на paywall
        $data['blocks'] = array_slice($blocks, 0, $paywallIndex);
        $truncatedJson = json_encode($data, JSON_UNESCAPED_UNICODE);

        return [
            'html' => render_editorjs_content($truncatedJson, true),
            'is_locked' => true,
        ];
    }
}

if (!function_exists('get_story_excerpt')) {
    /**
     * Формирует краткое превью статьи для ленты.
     * Пропускает первый блок H1/H2 (он уже выведен как title).
     */
    function get_story_excerpt(array $story, int $maxBlocks = 2): string 
    {
        $json = $story['description_json'] ?? null;
        $text = $story['description_text'] ?? '';

        if ($json) {
            $data = json_decode($json, true);
            if ($data && isset($data['blocks']) && is_array($data['blocks'])) {
                $html = '';
                $blocksAdded = 0;
                $firstHeaderSkipped = false;

                foreach ($data['blocks'] as $block) {
                    if ($blocksAdded >= $maxBlocks) break;

                    $type = $block['type'] ?? '';
                    $d = $block['data'] ?? [];
                    $content = $d['text'] ?? $d['content'] ?? '';

                    // Пропускаем первый заголовок
                    if (!$firstHeaderSkipped && $type === 'header') {
                        $level = (int)($d['level'] ?? 2);
                        if ($level <= 2) {
                            $firstHeaderSkipped = true;
                            continue;
                        }
                    }

                    if ($type === 'paragraph') {
                        $clean = strip_tags($content, '<b><strong><i><em><a><code>');
                        $html .= "<p class=\"story-excerpt\">{$clean}</p>";
                        $blocksAdded++;
                    } elseif ($type === 'quote') {
                        $clean = strip_tags($content, '<b><strong><i><em>');
                        $html .= "<blockquote class=\"story-excerpt\"><p>{$clean}</p></blockquote>";
                        $blocksAdded++;
                    }
                }
                
                if (trim($html) !== '') {
                    return trim($html);
                }
            }
        }

        // Fallback: если JSON нет или пуст
        if ($text) {
            $cleanText = mb_substr(strip_tags($text), 0, 200);
            if (mb_strlen(strip_tags($text)) > 200) {
                $cleanText .= '...';
            }
            return '<p class="story-excerpt">' . htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return '';
    }
}

if (!function_exists('get_story_first_image')) {
    /**
     * Извлекает URL первой картинки из JSON статьи для превью в ленте.
     * Автоматически возвращает версию нужного размера (по умолчанию 'small').
     * 
     * Поддерживает размеры: 'small', 'medium', 'large', 'original'
     * 
     * @param array $story Массив статьи
     * @param string $size Размер изображения: small|medium|large|original
     * @return string|null URL изображения или null если не найдено
     */
    function get_story_first_image(array $story, string $size = 'small'): ?string 
    {
        $json = $story['description_json'] ?? null;
        if (!$json) return null;

        $data = json_decode($json, true);
        if (!$data || !isset($data['blocks']) || !is_array($data['blocks'])) {
            return null;
        }

        // Ищем первую картинку
        foreach ($data['blocks'] as $block) {
            if (($block['type'] ?? '') === 'image') {
                $url = $block['data']['file']['url'] ?? null;
                
                // Проверяем, что URL не пустой и валидный
                if (empty($url) || (!str_starts_with($url, '/') && !str_starts_with($url, 'http'))) {
                    continue;
                }

                // Возвращаем URL нужного размера
                return get_image_variant_url($url, $size);
            }
        }
        
        return null;
    }
}

if (!function_exists('get_image_variant_url')) {
    /**
     * Генерирует URL для нужного размера изображения на основе основного URL.
     * 
     * Конвенция именования файлов:
     * - original: /uploads/stories/2026/08/abc123.webp
     * - large:    /uploads/stories/2026/08/abc123_large.webp
     * - medium:   /uploads/stories/2026/08/abc123_medium.webp
     * - small:    /uploads/stories/2026/08/abc123_small.webp
     * 
     * @param string $url Основной URL изображения
     * @param string $size Размер: small|medium|large|original
     * @return string URL нужной версии
     */
    function get_image_variant_url(string $url, string $size = 'original'): string
    {
        // Допустимые размеры
        $validSizes = ['small', 'medium', 'large', 'original'];
        if (!in_array($size, $validSizes, true)) {
            $size = 'original';
        }

        // Если оригинал — возвращаем как есть
        if ($size === 'original') {
            return $url;
        }

        // Парсим URL
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        
        // Разбиваем путь на компоненты
        $pathInfo = pathinfo($path);
        $dir = $pathInfo['dirname'] ?? '';
        $baseName = $pathInfo['filename'] ?? '';
        $extension = $pathInfo['extension'] ?? 'webp';

        // 🆕 Удаляем суффикс размера, если он уже есть (на случай повторного вызова)
        $baseName = preg_replace('/_(small|medium|large)$/', '', $baseName);

        // Формируем новое имя файла с суффиксом
        $newFileName = $baseName . '_' . $size . '.' . $extension;
        $newPath = ($dir === '.' || $dir === '') 
            ? $newFileName 
            : $dir . '/' . $newFileName;

        // Собираем URL обратно
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host = $parsedUrl['host'] ?? '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return $scheme . $host . $port . $newPath . $query . $fragment;
    }
}

if (!function_exists('layout')) {
    /**
     * Хелпер для установки макета страницы.
     * 
     * @param string $layout Макет (используйте константы Layout::WIDE и т.д.)
     */
    function layout(string $layout): void
    {
        Layout::set($layout);
    }
}

if (!function_exists('layout_class')) {
    /**
     * Получить CSS-класс контейнера для текущего макета.
     */
    function layout_class(): string
    {
        return Layout::getClass();
    }
}

if (!function_exists('layout_body_class')) {
    /**
     * Получить CSS-класс для body.
     */
    function layout_body_class(): string
    {
        return Layout::getBodyClass();
    }
}