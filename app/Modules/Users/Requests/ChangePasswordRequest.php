<?php

declare(strict_types=1);

namespace App\Modules\Users\Requests;

use W3a\Core\Http\FormRequest;

/**
 * Валидация формы изменения пароля.
 */
class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password'          => 'required',
            'new_password'              => 'required|min:6|max:255',
            'new_password_confirmation' => 'required|match:new_password',
        ];
    }

    public function fillable(): array
    {
        return [
            'current_password',
            'new_password',
            'new_password_confirmation',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'          => 'Текущий пароль обязателен для заполнения',
            'new_password.required'              => 'Новый пароль обязателен для заполнения',
            'new_password.min'                   => 'Новый пароль должен содержать минимум 6 символов',
            'new_password.max'                   => 'Новый пароль слишком длинный',
            'new_password_confirmation.required' => 'Подтверждение пароля обязательно',
            'new_password_confirmation.match'    => 'Пароли не совпадают',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password'          => 'текущий пароль',
            'new_password'              => 'новый пароль',
            'new_password_confirmation' => 'подтверждение пароля',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}