<?php

declare(strict_types=1);

namespace App\Modules\Stories\Requests;

use W3a\Core\Http\FormRequest;

class UpdateStoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description'       => 'required',
            'tags'              => '',
            'user_is_following' => '',
            'paywall_type'      => 'in:none,members,subscribers',
            'action'            => '',
        ];
    }

    public function fillable(): array
    {
        return [
            'description',
            'tags',
            'user_is_following',
            'paywall_type',
            'action',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Статья не может быть пустой',
            'paywall_type.in'      => 'Недопустимый тип paywall',
        ];
    }
}