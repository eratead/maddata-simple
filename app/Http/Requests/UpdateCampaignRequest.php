<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'client_id' => 'required|exists:clients,id',
            'expected_impressions' => 'nullable|integer|min:0|max:1000000000',
            'budget' => 'nullable|integer|min:0|max:1000000000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'required_sizes' => 'nullable|string|max:1000',
            'creative_optimization' => 'boolean',
            'status' => 'required|in:active,paused',
            'targeting_rules' => 'nullable|array',
            'targeting_rules.genders' => 'nullable|array',
            'targeting_rules.genders.*' => 'nullable|string|in:male,female,unknown',
            'targeting_rules.ages' => 'nullable|array',
            'targeting_rules.ages.*' => 'nullable|string|in:13-17,18-24,25-34,35-44,45-54,55-64,65+',
            'targeting_rules.incomes' => 'nullable|array',
            'targeting_rules.incomes.*' => 'nullable|string|in:0-195K,195-220K,220-245K,245K+',
            'targeting_rules.countries' => 'nullable|array',
            'targeting_rules.countries.*' => 'nullable|string|max:100',
            'targeting_rules.regions' => 'nullable|array',
            'targeting_rules.regions.*' => 'nullable|string|max:100',
            'targeting_rules.cities' => 'nullable|array',
            'targeting_rules.cities.*' => 'nullable|string|max:100',
            'targeting_rules.device_types' => 'nullable|array',
            'targeting_rules.device_types.*' => 'nullable|string|in:Mobile,Tablet,Desktop,CTV',
            'targeting_rules.os' => 'nullable|array',
            'targeting_rules.os.*' => 'nullable|string|in:iOS,Android,Windows,macOS',
            'targeting_rules.connection_types' => 'nullable|array',
            'targeting_rules.connection_types.*' => 'nullable|string|in:WiFi,Cellular',
            'targeting_rules.environments' => 'nullable|array',
            'targeting_rules.environments.*' => 'nullable|string|in:In-App,Mobile Web',
            'targeting_rules.allowlist' => 'nullable|string|max:65535',
            'targeting_rules.blocklist' => 'nullable|string|max:65535',
            'targeting_rules.days' => 'nullable|array',
            'targeting_rules.days.*' => 'nullable|string|in:Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'targeting_rules.time_start' => 'nullable|date_format:H:i',
            'targeting_rules.time_end' => 'nullable|date_format:H:i',
            'locations' => 'nullable|array',
            'locations.*.name' => 'nullable|string|max:255',
            'locations.*.lat' => 'required_with:locations.*|numeric|between:-90,90',
            'locations.*.lng' => 'required_with:locations.*|numeric|between:-180,180',
            'locations.*.radius_meters' => 'nullable|integer|min:100|max:100000',
        ];
    }
}
