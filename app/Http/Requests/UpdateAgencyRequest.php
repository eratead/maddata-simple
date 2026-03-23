<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:agencies,name,'.$this->route('agency')->id],
        ];
    }
}
