<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:open,in_progress,closed',
            'category' => 'sometimes|in:major,minor,observasi',
            'deadline' => 'nullable|date',
            'admin_notes' => 'nullable|string|max:2000',
        ];
    }
}
