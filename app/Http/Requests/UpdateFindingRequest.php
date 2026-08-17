<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFindingRequest extends FormRequest
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
            'kategori' => 'sometimes|in:major,minor,observasi',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string',
            'pic_id' => 'sometimes|exists:users,id',
        ];
    }
}
