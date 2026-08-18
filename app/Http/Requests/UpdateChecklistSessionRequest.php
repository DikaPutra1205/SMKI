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
            'nama_sesi' => 'sometimes|required|string|max:255',
            'periode' => 'nullable|string|max:100',
            'konteks_penilaian' => 'nullable|string',
            'unit_id' => 'sometimes|required|exists:work_units,id',
            'framework_id' => 'nullable|exists:frameworks,id',
            'auditor_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|required|in:draft,in_progress,submitted,verified,closed',
            'catatan' => 'nullable|string',
        ];
    }
}
