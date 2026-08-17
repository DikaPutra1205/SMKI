<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreComplianceEvidenceRequest extends FormRequest
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
            'bukti_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'uploaded_by' => 'required|exists:users,id',
        ];
    }
}
