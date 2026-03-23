<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
