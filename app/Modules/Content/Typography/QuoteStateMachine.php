<?php

declare(strict_types=1);

namespace App\Modules\Content\Typography;

/**
 * Конечный автомат (FSM) для замены кавычек на типографские.
 * 
 * Правила русской типографики:
 * - Первый уровень вложенности: «ёлочки» 
 * - Второй уровень вложенности: „лапки"
 * - Пример: Он сказал: «Привет, „мир"!»
 * 
 * Почему FSM, а не регулярки?
 * Регулярки не умеют корректно обрабатывать:
 * - Вложенные кавычки: "Он сказал "привет"" → «Он сказал „привет"»
 * - Апострофы в словах: it's, don't (не должны меняться)
 * - Дюймы: 3" монитор, 15" экран (не должны меняться)
 * - Непарные кавычки: "Привет → «Привет (если не нашли пару)
 * 
 * Состояния автомата:
 * - OUTSIDE: вне кавычек
 * - INSIDE_LEVEL_1: внутри кавычек первого уровня («ёлочки»)
 * - INSIDE_LEVEL_2: внутри кавычек второго уровня („лапки")
 * 
 * Переходы:
 * - OUTSIDE + открывающая кавычка → INSIDE_LEVEL_1 (ставим «)
 * - INSIDE_LEVEL_1 + открывающая → INSIDE_LEVEL_2 (ставим „)
 * - INSIDE_LEVEL_2 + закрывающая → INSIDE_LEVEL_1 (ставим ")
 * - INSIDE_LEVEL_1 + закрывающая → OUTSIDE (ставим »)
 * 
 * Использование:
 *   $fsm = new QuoteStateMachine();
 *   $fsm->process('Он сказал: "Привет"');  // → 'Он сказал: «Привет»'
 *   $fsm->process('it\'s a test');          // → 'it\'s a test' (апостроф не меняется)
 *   $fsm->process('Экран 15"');             // → 'Экран 15"' (дюймы не меняются)
 */
class QuoteStateMachine
{
    /**
     * Состояние: вне кавычек
     */
    private const OUTSIDE = 0;

    /**
     * Состояние: внутри кавычек первого уровня («ёлочки»)
     */
    private const INSIDE_LEVEL_1 = 1;

    /**
     * Состояние: внутри кавычек второго уровня („лапки")
     */
    private const INSIDE_LEVEL_2 = 2;

    /**
     * Обработать текст: заменить кавычки на типографские.
     * 
     * Алгоритм:
     * 1. Проходим по каждому символу текста
     * 2. Если символ — кавычка, анализируем контекст (предыдущий/следующий символы)
     * 3. Определяем: открывающая это кавычка или закрывающая
     * 4. На основе текущего состояния FSM выбираем замену
     * 
     * @param string $text Текст для обработки
     * @return string Обработанный текст
     */
    public function process(string $text): string
    {
        // Пустой текст не обрабатываем
        if (trim($text) === '') {
            return $text;
        }

        $result = '';
        $state = self::OUTSIDE;
        $len = mb_strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);

            // Обрабатываем только кавычки (прямые и уже типографские)
            if (!$this->isQuoteChar($char)) {
                $result .= $char;
                continue;
            }

            // Контекст: предыдущий и следующий символы
            $prev = $i > 0 ? mb_substr($text, $i - 1, 1) : '';
            $next = $i < $len - 1 ? mb_substr($text, $i + 1, 1) : '';

            // Специальный случай 1: апостроф внутри слова (it's, don't)
            // НЕ заменяем, чтобы не ломать английский текст
            if ($char === "'" && $this->isInsideWord($prev, $next)) {
                $result .= $char;
                continue;
            }

            // Специальный случай 2: дюймы после числа (15", 3")
            // НЕ заменяем, чтобы не ломать размеры
            if (($char === '"' || $char === '«' || $char === '»') && preg_match('/\d$/u', $prev)) {
                $result .= '"'; // Оставляем прямую кавычку как символ дюйма
                continue;
            }

            // Определяем замену на основе FSM
            $replacement = $this->decideReplacement($char, $state, $prev, $next);
            $result .= $replacement['char'];
            $state = $replacement['new_state'];
        }

        return $result;
    }

    /**
     * Определить, на что заменить кавычку и в какое состояние перейти.
     * 
     * Логика определения открывающая/закрывающая:
     * - Открывающая: перед кавычкой пробел/знак/начало, после — буква/цифра
     *   Примеры: `"Привет"`, `Сказал: "Привет"`, `("текст"`
     * - Закрывающая: перед кавычкой буква/цифра, после — пробел/знак/конец
     *   Примеры: `"Привет"`, `"текст."`, `"текст")`
     * 
     * @param string $char Символ кавычки
     * @param int $state Текущее состояние FSM
     * @param string $prev Предыдущий символ
     * @param string $next Следующий символ
     * @return array{char: string, new_state: int} Замена и новое состояние
     */
    private function decideReplacement(string $char, int $state, string $prev, string $next): array
    {
        // Анализ контекста
        $isLetterBefore = (bool) preg_match('/[\p{L}\p{N}]/u', $prev);
        $isLetterAfter = (bool) preg_match('/[\p{L}\p{N}]/u', $next);
        $isSpaceBefore = $this->isSpaceOrPunct($prev);
        $isSpaceAfter = $this->isSpaceOrPunct($next);

        // Определяем: открывающая или закрывающая кавычка
        $isOpening = $isSpaceBefore && $isLetterAfter;
        $isClosing = $isLetterBefore && $isSpaceAfter;

        // Однозначно открывающая кавычка
        if ($isOpening && !$isClosing) {
            if ($state === self::OUTSIDE) {
                return ['char' => '«', 'new_state' => self::INSIDE_LEVEL_1];
            }
            if ($state === self::INSIDE_LEVEL_1) {
                return ['char' => '„', 'new_state' => self::INSIDE_LEVEL_2];
            }
            // Уровень 2 и выше — оставляем « (редкий случай)
            return ['char' => '«', 'new_state' => $state];
        }

        // Однозначно закрывающая кавычка
        if ($isClosing && !$isOpening) {
            if ($state === self::INSIDE_LEVEL_2) {
                return ['char' => '"', 'new_state' => self::INSIDE_LEVEL_1];
            }
            if ($state === self::INSIDE_LEVEL_1) {
                return ['char' => '»', 'new_state' => self::OUTSIDE];
            }
            // Вне кавычек — закрываем принудительно
            return ['char' => '»', 'new_state' => self::OUTSIDE];
        }

        // Неоднозначный случай: эвристика на основе текущего состояния
        // Если мы внутри кавычек и перед символом буква/закрывающая кавычка —
        // скорее всего это закрывающая кавычка
        if ($state !== self::OUTSIDE && ($isLetterBefore || $prev === '»' || $prev === '"')) {
            if ($state === self::INSIDE_LEVEL_2) {
                return ['char' => '"', 'new_state' => self::INSIDE_LEVEL_1];
            }
            return ['char' => '»', 'new_state' => self::OUTSIDE];
        }

        // Неоднозначный случай: мы внутри кавычек, а перед символом
        // стоит знак препинания (?, !, ., ,) — это тоже закрывающая кавычка
        if ($state !== self::OUTSIDE) {
            if ($state === self::INSIDE_LEVEL_2) {
                return ['char' => '"', 'new_state' => self::INSIDE_LEVEL_1];
            }
            return ['char' => '»', 'new_state' => self::OUTSIDE];
        }

        // По умолчанию (вне кавычек) считаем кавычку открывающей
        return ['char' => '«', 'new_state' => self::INSIDE_LEVEL_1];
    }

    /**
     * Проверить, является ли символ кавычкой (любого типа).
     * 
     * @param string $char Символ для проверки
     * @return bool
     */
    private function isQuoteChar(string $char): bool
    {
        return $char === '"'    // Прямая кавычка
            || $char === "'"    // Апостроф/одинарная кавычка
            || $char === '«'    // Ёлочка открывающая
            || $char === '»'    // Ёлочка закрывающая
            || $char === '„'    // Лапка открывающая
            || $char === '"';   // Лапка закрывающая
    }

    /**
     * Проверить: символ является пробелом, знаком препинания или началом/концом строки?
     * 
     * Используется для определения открывающая/закрывающая кавычка.
     * 
     * @param string $char Символ для проверки
     * @return bool
     */
    private function isSpaceOrPunct(string $char): bool
    {
        // Начало/конец строки считаем "пробелом"
        if ($char === '') {
            return true;
        }

        // Пробельные символы
        if ($char === ' ' || $char === "\n" || $char === "\t" || $char === "\r") {
            return true;
        }

        // Знаки препинания и разделители (Unicode категории P и Z)
        return (bool) preg_match('/[\p{P}\p{Z}]/u', $char);
    }

    /**
     * Проверить, находится ли апостроф внутри слова (например, it's, don't).
     * 
     * Если и перед, и после символа стоят буквы — это апостроф,
     * а не кавычка. Не заменяем.
     * 
     * @param string $prev Предыдущий символ
     * @param string $next Следующий символ
     * @return bool true, если это апостроф внутри слова
     */
    private function isInsideWord(string $prev, string $next): bool
    {
        return (bool) preg_match('/\p{L}/u', $prev) 
            && (bool) preg_match('/\p{L}/u', $next);
    }
}