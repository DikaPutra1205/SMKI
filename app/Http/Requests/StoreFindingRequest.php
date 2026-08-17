<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFindingRequest extends FormRequest
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
            'unit_id' => 'required|exists:work_units,id',
            'pic_id' => 'required|exists:users,id',
            'admin_id' => 'nullable|exists:users,id',
            'kategori' => 'required|in:major,minor,observasi',
            'status' => 'sometimes|in:open,in_progress,closed',
            'deadline' => 'nullable|date|after:today',
            'catatan_admin' => 'nullable|string',
        ];
    }
}
