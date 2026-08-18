<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'risk_level' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:open,mitigated,accepted',
            'mitigation_plan' => 'nullable|string|max:3000',
            'risk_owner' => 'nullable|string|max:255',
        ];
    }
}
