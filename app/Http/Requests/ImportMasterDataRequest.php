<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240', // 10MB
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'file Excel master data',
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
        ];
    }
}
