<?php

namespace App\Http\Requests;

use App\Models\ChecklistEntry;
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
            'status' => 'required|in:'.implode(',', [
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_PARTIAL,
                ChecklistEntry::STATUS_NON_COMPLIANT,
                ChecklistEntry::STATUS_NA,
            ]),
            'admin_notes' => 'nullable|string|max:2000',
        ];
    }
}
