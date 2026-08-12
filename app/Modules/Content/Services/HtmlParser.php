<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

/**
 * Парсер HTML для выделения защищённых зон.
 * 
 * Задача: разделить HTML на сегменты, чтобы типограф мог обработать
 * только текстовое содержимое, НЕ трогая:
 * 
 * 1. Содержимое защищённых тегов (<code>, <pre>, <script>, <style>)
 *    — там находится код, который нельзя искажать
 * 
 * 2. Сами HTML-теги и их атрибуты
 *    — замена кавычек в href="..." сломает ссылку
 * 
 * Алгоритм:
 * - Проход по строке, поиск символов '<'
 * - Если встретили открывающий тег — анализируем его
 * - Если это защищённый тег (<code>, <pre>...) — всё его содержимое
 *   помечается как protected до закрывающего тега
 * - Обычные теги (<p>, <strong>) помечаются как protected только сами
 * 
 * Формат сегмента:
 *   [
 *     'type' => 'text'|'protected',
 *     'content' => string,
 *     'kind' => 'tag'|'block' (только для protected)
 *   ]
 * 
 * Использование:
 *   $parser = new HtmlParser();
 *   $segments = $parser->parse('<p>Текст <code>"не менять"</code></p>');
 *   // → [
 *   //   ['type' => 'protected', 'content' => '<p>', 'kind' => 'tag'],
 *   //   ['type' => 'text', 'content' => 'Текст '],
 *   //   ['type' => 'protected', 'content' => '<code>', 'kind' => 'tag'],
 *   //   ['type' => 'protected', 'content' => '"не менять"', 'kind' => 'block'],
 *   //   ['type' => 'protected', 'content' => '</code>', 'kind' => 'tag'],
 *   //   ['type' => 'protected', 'content' => '</p>', 'kind' => 'tag'],
 *   // ]
 */
class HtmlParser
{
    /**
     * Теги, содержимое которых защищено от типографа.
     * 
     * - code, pre — блоки кода (Editor.js, Markdown)
     * - script, style — JS/CSS внутри HTML
     * - textarea — содержимое форм
     * - kbd, var, samp — семантические теги кода (HTML5)
     */
    private const PROTECTED_TAGS = [
        'code', 'pre', 'script', 'style', 'textarea', 'kbd', 'var', 'samp'
    ];

    /**
     * Разобрать HTML на сегменты.
     * 
     * @param string $html HTML-строка для разбора
     * @return array<array{type: string, content: string, kind?: string}> Массив сегментов
     */
    public function parse(string $html): array
    {
        // Пустой HTML не разбираем
        if (trim($html) === '') {
            return [];
        }

        $segments = [];
        $pos = 0;
        $len = strlen($html);

        while ($pos < $len) {
            // Ищем начало следующего тега
            $tagStart = strpos($html, '<', $pos);

            // Тегов больше нет — остаток является текстом
            if ($tagStart === false) {
                $text = substr($html, $pos);
                if ($text !== '') {
                    $segments[] = ['type' => 'text', 'content' => $text];
                }
                break;
            }

            // Текст перед тегом
            if ($tagStart > $pos) {
                $segments[] = [
                    'type' => 'text',
                    'content' => substr($html, $pos, $tagStart - $pos),
                ];
            }

            // Ищем конец тега (символ '>')
            $tagEnd = strpos($html, '>', $tagStart);
            if ($tagEnd === false) {
                // Некорректный HTML (тег не закрыт) — берём остаток как текст
                $segments[] = ['type' => 'text', 'content' => substr($html, $tagStart)];
                break;
            }

            // Извлекаем тег целиком (например, '<p class="intro">')
            $tag = substr($html, $tagStart, $tagEnd - $tagStart + 1);
            $segments[] = ['type' => 'protected', 'content' => $tag, 'kind' => 'tag'];

            // Проверяем: является ли тег защищённым блоком?
            $tagName = $this->extractTagName($tag);
            if ($tagName !== null && in_array(strtolower($tagName), self::PROTECTED_TAGS, true)) {
                // Ищем соответствующий закрывающий тег
                $closingTag = '</' . $tagName . '>';
                $closingPos = stripos($html, $closingTag, $tagEnd + 1);

                if ($closingPos !== false) {
                    // Всё содержимое между тегами — защищено
                    $protectedContent = substr($html, $tagEnd + 1, $closingPos - $tagEnd - 1);
                    $segments[] = [
                        'type' => 'protected',
                        'content' => $protectedContent,
                        'kind' => 'block',
                    ];

                    // Закрывающий тег тоже защищён
                    $segments[] = [
                        'type' => 'protected',
                        'content' => $closingTag,
                        'kind' => 'tag',
                    ];

                    // Перескакиваем за закрывающий тег
                    $pos = $closingPos + strlen($closingTag);
                    continue;
                }
                // Если закрывающего тега нет — обрабатываем как обычный тег
            }

            // Переходим к следующему символу после тега
            $pos = $tagEnd + 1;
        }

        return $segments;
    }

    /**
     * Собрать сегменты обратно в HTML-строку.
     * 
     * @param array $segments Массив сегментов от parse()
     * @return string Собранная HTML-строка
     */
    public function assemble(array $segments): string
    {
        return implode('', array_column($segments, 'content'));
    }

    /**
     * Извлечь имя тега из HTML-строки тега.
     * 
     * @param string $tag Строка тега, например '<p class="intro">' или '</p>'
     * @return string|null Имя тега ('p') или null, если не удалось извлечь
     * 
     * @example
     *   $this->extractTagName('<p class="x">'); // → 'p'
     *   $this->extractTagName('</code>');       // → 'code'
     *   $this->extractTagName('<br/>');         // → 'br'
     */
    private function extractTagName(string $tag): ?string
    {
        if (preg_match('/^<\/?([a-zA-Z][a-zA-Z0-9]*)/', $tag, $matches)) {
            return $matches[1];
        }
        return null;
    }
}