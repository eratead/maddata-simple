<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'landing' => 'required|url|max:2048',
            'status' => 'required|boolean',
        ];
    }
}
