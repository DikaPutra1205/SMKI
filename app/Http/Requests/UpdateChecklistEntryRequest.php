<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistEntryRequest extends FormRequest
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
            'status' => 'sometimes|in:sesuai,tidak_sesuai,belum_diterapkan,tidak_berlaku',
            'pic_id' => 'sometimes|exists:users,id',
            'catatan_admin' => 'nullable|string',
        ];
    }
}
