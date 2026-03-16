<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name'                   => 'required|string|min:2|max:255',
            'client_id'              => 'required|exists:clients,id',
            'expected_impressions'   => 'nullable|integer|min:0|max:1000000000',
            'budget'                 => 'nullable|integer|min:0|max:1000000000',
            'start_date'             => 'nullable|date|after_or_equal:today',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'required_sizes'         => 'nullable|string|max:1000',
            'creative_optimization'  => 'boolean',
            'status'                 => 'required|in:active,paused',
        ];
    }
}
