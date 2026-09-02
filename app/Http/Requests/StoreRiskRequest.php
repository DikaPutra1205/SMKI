<?php

namespace App\Http\Requests;

use App\Models\Risk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unitId = $this->input('unit_id');

        return $this->user()?->can('create', [Risk::class, $unitId ? (int) $unitId : null]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'control_id' => 'required|exists:controls,id',
            'unit_id' => 'nullable|exists:work_units,id',
            'level_risiko' => 'sometimes|required_without:risk_level|in:low,medium,high,critical',
            'risk_level' => 'sometimes|required_without:level_risiko|in:low,medium,high,critical',
            'pemilik_risiko' => 'sometimes|required_without:risk_owner|string|max:255',
            'risk_owner' => 'sometimes|required_without:pemilik_risiko|string|max:255',
            'rencana_mitigasi' => 'nullable|string|max:3000',
            'mitigation_plan' => 'nullable|string|max:3000',
            'status' => 'sometimes|in:open,mitigated,accepted',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string|max:3000',
            'admin_notes' => 'nullable|string|max:3000',
        ];
    }
}
