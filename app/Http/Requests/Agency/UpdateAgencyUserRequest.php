<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAgencyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()],
            'role_id' => ['required', 'exists:roles,id'],
            'access_all_clients' => ['boolean'],
            'clients' => ['array'],
            'clients.*' => ['integer', 'exists:clients,id'],
            'is_active' => ['boolean'],
        ];
    }
}
