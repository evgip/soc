<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Modules\Content\Typography\QuoteStateMachine;
use App\Modules\Content\Typography\DashProcessor;
use App\Modules\Content\Typography\SimpleRules;

/**
 * Типограф для русского текста (Medium-style).
 * 
 * Применяет типографские правила к HTML-контенту, защищая при этом:
 * - Содержимое тегов <code>, <pre>, <script>, <style>
 * - Атрибуты HTML-тегов (href, src, alt и т.д.)
 * 
 * Архитектура:
 * - HtmlParser разделяет HTML на "текст" и "защищённые зоны"
 * - К защищённым зонам типографика НЕ применяется
 * - К тексту применяется пайплайн: SimpleRules → QuoteStateMachine → DashProcessor
 * 
 * Использование:
 *   $typo = new TypographyService();
 *   $result = $typo->apply('<p>Он сказал: "Привет" - это тест...</p>');
 *   // → <p>Он сказал: «Привет» — это тест…</p>
 * 
 * Важно:
 * - Типограф применяется при РЕНДЕР, а не при сохранении в БД
 * - Исходный контент (JSON/Markdown) остаётся неизменным
 * - Редактирование в Editor.js работает с оригинальным текстом
 * 
 * @see HtmlParser
 * @see QuoteStateMachine
 * @see DashProcessor
 * @see SimpleRules
 */
class TypographyService
{
    /**
     * Парсер HTML для выделения защищённых зон
     */
    private HtmlParser $htmlParser;

    /**
     * Конечный автомат для замены кавычек
     */
    private QuoteStateMachine $quotes;

    /**
     * Токенайзер для замены дефисов на тире
     */
    private DashProcessor $dashes;

    /**
     * Простые правила: многоточие, спецсимволы, предлоги
     */
    private SimpleRules $simple;

    public function __construct()
    {
        $this->htmlParser = new HtmlParser();
        $this->quotes = new QuoteStateMachine();
        $this->dashes = new DashProcessor();
        $this->simple = new SimpleRules();
    }

    /**
     * Применить типографику к HTML-строке.
     * 
     * Защищённые зоны (<code>, <pre>, атрибуты тегов) не обрабатываются.
     * 
     * @param string $html HTML для обработки
     * @return string Обработанный HTML
     * 
     * @example
     *   $typo->apply('<p>"Привет" - мир</p>');
     *   // → '<p>«Привет» — мир</p>'
     *   
     *   $typo->apply('<code>"test" - 5</code>');
     *   // → '<code>"test" - 5</code>' (код не трогается)
     */
    public function apply(string $html): string
    {
        // Пустой контент не обрабатываем
        if (trim($html) === '') {
            return $html;
        }

        // Разбиваем HTML на сегменты: текст + защищённые зоны
        $segments = $this->htmlParser->parse($html);

        // Применяем типографику только к текстовым сегментам
        foreach ($segments as &$segment) {
            if ($segment['type'] === 'text') {
                $segment['content'] = $this->processText($segment['content']);
            }
            // Защищённые сегменты (kind=tag, kind=block) оставляем как есть
        }
        unset($segment);

        // Собираем HTML обратно
        return $this->htmlParser->assemble($segments);
    }

    /**
     * Применить типографику к чистому тексту (без HTML).
     * 
     * Используется для обработки Markdown ДО парсинга в HTML,
     * либо для любых текстовых данных (например, title, description).
     * 
     * @param string $text Текст для обработки
     * @return string Обработанный текст
     * 
     * @example
     *   $typo->applyToPlainText('Он сказал "привет"...');
     *   // → 'Он сказал «привет»…'
     */
    public function applyToPlainText(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        return $this->processText($text);
    }

    /**
     * Пайплайн обработки текста.
     * 
     * ВАЖЕН ПОРЯДОК применения правил:
     * 1. SimpleRules — многоточие, спецсимволы, предлоги
     *    (до кавычек, чтобы не ломать контекст)
     * 2. QuoteStateMachine — кавычки через FSM
     *    (после многоточия, т.к. "…" влияет на определение закрытия кавычки)
     * 3. DashProcessor — тире через токенайзер
     *    (последним, т.к. работает с финальным текстом)
     * 
     * @param string $text Текст для обработки
     * @return string Обработанный текст
     */
    private function processText(string $text): string
    {
        // Пустой текст не обрабатываем
        if (trim($text) === '') {
            return $text;
        }

        // 1. Простые правила (многоточие, спецсимволы, неразрывные пробелы)
        $text = $this->simple->apply($text);

        // 2. Кавычки через конечный автомат
        $text = $this->quotes->process($text);

        // 3. Тире через токенайзер
        $text = $this->dashes->process($text);

        return $text;
    }
}