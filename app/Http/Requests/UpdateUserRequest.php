<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$this->route('user')->id],
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()],
            'role_id' => ['nullable', 'exists:roles,id'],
            'agencies' => ['nullable', 'array'],
            'agencies.*.agency_id' => ['required', 'exists:agencies,id'],
            'agencies.*.access_all_clients' => ['required', 'boolean'],
            'agencies.*.clients' => ['nullable', 'array'],
            'agencies.*.clients.*' => ['integer', 'exists:clients,id'],
        ];
    }
}
