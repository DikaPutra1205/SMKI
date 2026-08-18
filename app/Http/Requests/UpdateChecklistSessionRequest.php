<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistSessionRequest extends FormRequest
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
            'konteks_penilaian' => 'sometimes|required|string|max:255',
            'periode' => 'nullable|string|max:100',
            'unit_id' => 'sometimes|required|exists:work_units,id',
            'framework_id' => 'nullable|exists:frameworks,id',
            'updated_by' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
        ];
    }
}
