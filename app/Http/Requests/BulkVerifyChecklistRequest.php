<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkVerifyChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'required|integer|exists:checklist_entries,id',
            'status' => 'prohibited',
            'decision' => 'required|in:approve,reject',
            'admin_notes' => 'required_if:decision,reject|nullable|string|max:2000',
        ];
    }
}
