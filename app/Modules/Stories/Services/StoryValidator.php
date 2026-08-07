<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Tags\Services\TagValidator;

/**
 * Валидатор данных статьи (Medium-стиль).
 * 
 * Используется в StoryService (create/update) и SuggestionService.
 * Отвечает только за проверку данных, не работает с БД напрямую.
 */
class StoryValidator
{
    private TagValidator $tagValidator;

    /**
     * Конструктор с инъекцией зависимостей.
     * 
     * @param TagValidator $tagValidator Валидатор тегов
     */
    public function __construct(TagValidator $tagValidator)
    {
        $this->tagValidator = $tagValidator;
    }

    /**
     * Полная валидация данных статьи.
     * В Medium-стиле заголовок извлекается из JSON в StoryService, 
     * поэтому здесь мы проверяем в основном наличие контента и теги.
     * 
     * @param array $data Данные для проверки
     * @param bool $isUpdate true если это обновление (некоторые поля опциональны)
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // 1. Проверка наличия контента (JSON от Editor.js)
        if (empty(trim($data['description'] ?? ''))) {
            $errors[] = 'Содержание статьи не может быть пустым.';
        }

        // 2. Валидация тегов (если они переданы)
        if (isset($data['tags'])) {
            $tagValidation = $this->tagValidator->validateForStory($data['tags']);
            if (!$tagValidation['valid']) {
                $errors[] = $tagValidation['error'];
            }
        }

        // 3. Валидация заголовка (Опционально: на случай, если он передается отдельно, 
        // например, через систему предложений изменений)
        if (!empty($data['title'])) {
            $titleError = $this->validateTitle($data['title']);
            if ($titleError) {
                $errors[] = $titleError;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Валидация заголовка (на случай, если он передается отдельной строкой).
     * 
     * @param string $title Заголовок
     * @return string|null Сообщение об ошибке или null
     */
    public function validateTitle(string $title): ?string
    {
        $minLength = config('validation.title_min_length', 5, 'int');
        $maxLength = config('validation.title_max_length', 150, 'int');

        if (mb_strlen($title) < $minLength) {
            return "Заголовок должен содержать как минимум {$minLength} символов.";
        }

        if (mb_strlen($title) > $maxLength) {
            return "Заголовок слишком длинный. Максимум {$maxLength} символов.";
        }

        return null;
    }

    /**
     * Валидация для предложения (Suggestion).
     * Проверяет только те поля, которые переданы.
     * 
     * @param array $proposedData Предлагаемые изменения
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validateForSuggestion(array $proposedData): array
    {
        $errors = [];

        // Заголовок (если предложен)
        if (isset($proposedData['title']) && !empty($proposedData['title'])) {
            $titleError = $this->validateTitle($proposedData['title']);
            if ($titleError) {
                $errors[] = $titleError;
            }
        }

        // Теги (если предложены)
        if (isset($proposedData['tag_ids'])) {
            $tagValidation = $this->tagValidator->validateForSuggestion($proposedData['tag_ids']);
            if (!$tagValidation['valid']) {
                $errors[] = $tagValidation['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}