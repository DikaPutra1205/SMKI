<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFrameworkRequest extends FormRequest
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
            'nama' => 'required|string|max:255|unique:frameworks,nama',
            'versi' => 'required|string|max:50',
            'file_dokumen' => 'nullable|file|mimes:pdf,docx,csv,xlsx|max:20480',
        ];
    }
}
