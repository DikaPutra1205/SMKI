<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistSessionRequest extends FormRequest
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
            'nama_sesi' => 'required|string|max:255',
            'unit_id' => 'required|exists:work_units,id',
            'framework_id' => 'nullable|exists:frameworks,id',
            'created_by' => 'nullable|exists:users,id',
            'auditor_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:draft,in_progress,submitted,verified,closed',
            'catatan' => 'nullable|string',
        ];
    }
}
