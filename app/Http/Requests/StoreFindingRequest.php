<?php

namespace App\Http\Requests;

use App\Models\Finding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Finding::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'control_id' => 'required|exists:controls,id',
            'unit_id' => 'required|exists:work_units,id',
            'pic_id' => 'nullable|exists:users,id',
            'admin_id' => 'nullable|exists:users,id',
            'kategori' => 'required|in:major,minor,observasi',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'deadline' => 'nullable|date',
            'catatan' => 'nullable|string|max:2000',
            'catatan_admin' => 'nullable|string|max:2000',
            'admin_notes' => 'nullable|string|max:2000',
        ];
    }
}
