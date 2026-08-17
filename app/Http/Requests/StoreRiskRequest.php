<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'control_id' => 'required|exists:controls,id',
            'level_risiko' => 'required|in:low,medium,high,critical',
            'pemilik_risiko' => 'required|string|max:255',
            'rencana_mitigasi' => 'nullable|string',
            'status' => 'sometimes|in:open,mitigated,accepted',
        ];
    }
}
