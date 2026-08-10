<?php

declare(strict_types=1);

namespace App\Modules\Users\Requests;

use W3a\Core\Http\FormRequest;

/**
 * Валидация формы обновления настроек профиля.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'                   => 'required|email|max:255',
            'bio'                     => 'max:1000',
            // Для чекбоксов НЕ ставим правила:
            // - если не отмечен — поле отсутствует в запросе, пропускается автоматически
            // - если отмечен — придёт "on" или "1", нам это подходит
            'notify_on_reply'         => '',
            'notify_on_story_comment' => '',
            'notify_on_mention'       => '',
            'notify_on_message'       => '',
            'email_notifications'     => '',
        ];
    }

    public function fillable(): array
    {
        return [
            'email',
            'bio',
            'notify_on_reply',
            'notify_on_story_comment',
            'notify_on_mention',
            'notify_on_message',
            'email_notifications',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен для заполнения',
            'email.email'    => 'Некорректный формат email',
            'email.max'      => 'Email слишком длинный',
            'bio.max'        => 'Биография не может превышать 1000 символов',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email',
            'bio'   => 'биография',
        ];
    }

}