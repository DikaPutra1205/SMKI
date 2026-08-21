<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFrameworkRequest extends FormRequest
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
            'nama' => ['sometimes', 'string', 'max:255', Rule::unique('frameworks', 'nama')->ignore($this->route('framework'))],
            'versi' => 'sometimes|string|max:50',
            'url_file' => 'nullable|url|max:500',
            'file_dokumen' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
        ];
    }
}
