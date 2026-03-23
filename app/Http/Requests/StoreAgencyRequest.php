<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:agencies,name'],
            'manager_name' => ['nullable', 'string', 'max:255', 'required_with:manager_email'],
            'manager_email' => ['nullable', 'email', 'unique:users,email', 'required_with:manager_name'],
            'manager_password' => ['nullable', 'required_with:manager_name', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'manager_name.required_with' => 'Manager name is required when creating an agency manager.',
            'manager_email.required_with' => 'Manager email is required when creating an agency manager.',
            'manager_password.required_with' => 'Manager password is required when creating an agency manager.',
        ];
    }
}
