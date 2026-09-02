<?php

namespace App\Http\Requests;

use App\Models\Risk;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $risk = $this->route('risk') ?? $this->route('id');

        if ($risk instanceof Risk) {
            return $this->user()?->can('update', $risk) ?? false;
        }

        if (is_numeric($risk)) {
            $riskModel = Risk::find($risk);
            if ($riskModel) {
                return $this->user()?->can('update', $riskModel) ?? false;
            }
        }

        return $this->user()?->hasPermissionTo('risk.update') || $this->user()?->isAdmin() || $this->user()?->isSuperAdmin() || $this->user()?->isKoordinator();
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
