<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFindingStatusRequest extends FormRequest
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
            'status' => 'required|in:open,in_progress,resolved,closed',
            'catatan' => 'required|string|min:3|max:2000',
            'admin_id' => 'sometimes|nullable|exists:users,id',
        ];
    }
}
