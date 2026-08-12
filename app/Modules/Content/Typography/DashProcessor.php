<?php

declare(strict_types=1);

namespace App\Modules\Content\Typography;

/**
 * Токенайзер для замены дефисов на типографские тире.
 * 
 * Почему токенайзер, а не регулярки?
 * Регулярки не могут корректно различить контексты:
 * - "что-то" — дефис в слове, НЕ менять
 * - "слово - слово" — тире между словами, заменить на " — "
 * - "10-20" — диапазон чисел, заменить на "10–20" (en-dash)
 * - "- Текст" в начале предложения — тире, заменить на "— Текст"
 * 
 * Алгоритм:
 * 1. Разбиваем текст на токены (слова, знаки, пробелы, дефисы)
 * 2. Для каждого дефиса анализируем СОСЕДНИЕ токены
 * 3. На основе контекста принимаем решение о замене
 * 
 * Типы тире в типографике:
 * - Дефис (-) — внутри слов: что-то, из-за
 * - Короткое тире (–, en-dash) — диапазоны чисел: 10–20
 * - Длинное тире (—, em-dash) — между словами: слово — слово
 * 
 * Использование:
 *   $processor = new DashProcessor();
 *   $processor->process('что-то');         // → 'что-то' (дефис в слове)
 *   $processor->process('слово - слово');  // → 'слово — слово'
 *   $processor->process('10-20 км');       // → '10–20 км' (en-dash)
 *   $processor->process('- Привет');       // → '— Привет' (начало предложения)
 */
class DashProcessor
{
    /**
     * Тип токена: слово или число (например, "привет", "42")
     */
    private const TOKEN_WORD = 'word';

    /**
     * Тип токена: знак препинания (например, ".", ",", "!")
     */
    private const TOKEN_PUNCT = 'punct';

    /**
     * Тип токена: пробельный символ
     */
    private const TOKEN_SPACE = 'space';

    /**
     * Тип токена: дефис/тире (-, –, —)
     */
    private const TOKEN_DASH = 'dash';

    /**
     * Тип токена: всё остальное
     */
    private const TOKEN_OTHER = 'other';

    /**
     * Обработать текст: заменить дефисы на тире там, где это уместно.
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

        // Разбиваем текст на токены
        $tokens = $this->tokenize($text);
        $result = '';

        // Проходим по токенам
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            // Не дефисы просто копируем в результат
            if ($token['type'] !== self::TOKEN_DASH) {
                $result .= $token['value'];
                continue;
            }

            // Для дефиса анализируем соседей (пропуская пробелы)
            $prev = $this->getPrev($tokens, $i);
            $next = $this->getNext($tokens, $i);

            // Принимаем решение о замене
            $result .= $this->decideDash($prev, $next);
        }

        return $result;
    }

    /**
     * Разбить текст на токены.
     * 
     * Токены:
     * - word: последовательность букв/цифр/подчёркиваний
     * - punct: одиночный знак препинания
     * - space: одиночный пробельный символ
     * - dash: дефис или тире
     * - other: всё остальное
     * 
     * @param string $text Текст для токенизации
     * @return array<array{type: string, value: string}> Массив токенов
     */
    private function tokenize(string $text): array
    {
        $tokens = [];
        $len = mb_strlen($text);
        $i = 0;

        while ($i < $len) {
            $char = mb_substr($text, $i, 1);

            // Дефис или тире — отдельный токен
            if ($char === '-' || $char === '–' || $char === '—') {
                $tokens[] = ['type' => self::TOKEN_DASH, 'value' => $char];
                $i++;
                continue;
            }

            // Слово/число: собираем подряд идущие буквы, цифры, подчёркивания
            if (preg_match('/[\p{L}\p{N}]/u', $char)) {
                $word = '';
                while ($i < $len && preg_match('/[\p{L}\p{N}_]/u', mb_substr($text, $i, 1))) {
                    $word .= mb_substr($text, $i, 1);
                    $i++;
                }
                $tokens[] = ['type' => self::TOKEN_WORD, 'value' => $word];
                continue;
            }

            // Пробельный символ
            if (preg_match('/[\p{Z}\s]/u', $char)) {
                $tokens[] = ['type' => self::TOKEN_SPACE, 'value' => $char];
                $i++;
                continue;
            }

            // Знак препинания
            if (preg_match('/\p{P}/u', $char)) {
                $tokens[] = ['type' => self::TOKEN_PUNCT, 'value' => $char];
                $i++;
                continue;
            }

            // Всё остальное
            $tokens[] = ['type' => self::TOKEN_OTHER, 'value' => $char];
            $i++;
        }

        return $tokens;
    }

    /**
     * Получить предыдущий НЕПРОБЕЛЬНЫЙ токен.
     * 
     * @param array $tokens Массив всех токенов
     * @param int $index Индекс текущего токена
     * @return array|null Предыдущий непробельный токен или null
     */
    private function getPrev(array $tokens, int $index): ?array
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if ($tokens[$i]['type'] !== self::TOKEN_SPACE) {
                return $tokens[$i];
            }
        }
        return null;
    }

    /**
     * Получить следующий НЕПРОБЕЛЬНЫЙ токен.
     * 
     * @param array $tokens Массив всех токенов
     * @param int $index Индекс текущего токена
     * @return array|null Следующий непробельный токен или null
     */
    private function getNext(array $tokens, int $index): ?array
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if ($tokens[$i]['type'] !== self::TOKEN_SPACE) {
                return $tokens[$i];
            }
        }
        return null;
    }

    /**
     * Принять решение о замене дефиса.
     * 
     * Правила (в порядке приоритета):
     * 1. Начало текста или после . ? ! : … → длинное тире с пробелом: "— "
     * 2. Между двумя числами → короткое тире (en-dash): "10–20"
     * 3. Между двумя словами → длинное тире с пробелами: "слово — слово"
     * 4. По умолчанию → длинное тире с пробелами: " — "
     * 
     * ВАЖНО: дефисы внутри слов (что-то) сюда не попадают,
     * потому что токенайзер не разбивает "что-то" на отдельные токены.
     * 
     * @param array|null $prev Предыдущий непробельный токен
     * @param array|null $next Следующий непробельный токен
     * @return string Замена для дефиса
     */
    private function decideDash(?array $prev, ?array $next): string
    {
        $prevVal = $prev['value'] ?? '';
        $prevType = $prev['type'] ?? null;
        $nextVal = $next['value'] ?? '';
        $nextType = $next['type'] ?? null;

        // Правило 1: начало текста или после конца предложения
        if ($prev === null || in_array($prevVal, ['.', '?', '!', ':', '…'], true)) {
            return '— ';
        }

        // Правило 2: диапазон чисел: "10-20" → "10–20" (en-dash)
        if (
            $prevType === self::TOKEN_WORD && is_numeric($prevVal) &&
            $nextType === self::TOKEN_WORD && is_numeric($nextVal)
        ) {
            return '–'; // Короткое тире без пробелов
        }

        // Правило 3: между словами: "слово - слово" → "слово — слово"
        if ($prevType === self::TOKEN_WORD && $nextType === self::TOKEN_WORD) {
            return ' — ';
        }

        // Правило 4: по умолчанию — длинное тире с пробелами
        return ' — ';
    }
}