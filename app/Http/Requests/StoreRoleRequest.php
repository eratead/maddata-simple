<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'permissions' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = array_keys(Role::availablePermissions());
        $raw = $this->input('permissions', []);

        if (is_array($raw)) {
            $this->merge([
                'permissions' => array_intersect_key($raw, array_flip($allowed)),
            ]);
        }
    }
}
