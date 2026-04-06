<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('audience'));
    }

    public function rules(): array
    {
        return [
            'main_category' => ['required', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'estimated_users' => ['nullable', 'integer', 'min:0'],
            'provider' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
