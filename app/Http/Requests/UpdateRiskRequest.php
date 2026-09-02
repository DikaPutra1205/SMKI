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
            'level_risiko' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:open,mitigated,accepted',
            'mitigation_plan' => 'nullable|string|max:3000',
            'rencana_mitigasi' => 'nullable|string|max:3000',
            'risk_owner' => 'nullable|string|max:255',
            'pemilik_risiko' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:work_units,id',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string|max:3000',
            'admin_notes' => 'nullable|string|max:3000',
        ];
    }
}
