<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        $user = $this->user();

        $agencyIdRules = ['required', 'exists:agencies,id'];

        // Non-admin users may only assign an agency they belong to
        if (! $user->hasPermission('is_admin')) {
            $allowedAgencyIds = $user->agencies->pluck('id')->all();
            $agencyIdRules[] = Rule::in($allowedAgencyIds);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'agency_id' => $agencyIdRules,
        ];
    }
}
