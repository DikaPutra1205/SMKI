<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskRequest extends FormRequest
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
            'level_risiko' => 'sometimes|in:low,medium,high,critical',
            'pemilik_risiko' => 'sometimes|string|max:255',
            'rencana_mitigasi' => 'nullable|string',
            'status' => 'sometimes|in:open,mitigated,accepted',
        ];
    }
}
