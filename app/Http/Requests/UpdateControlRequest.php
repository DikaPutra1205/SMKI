<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $controlId = $this->route('control')?->id ?? $this->route('control');

        return [
            'framework_id' => ['required', 'integer', 'exists:frameworks,id'],
            'kode_klausul' => [
                'required',
                'string',
                'max:20',
                Rule::unique('controls', 'kode_klausul')
                    ->where('framework_id', $this->input('framework_id'))
                    ->whereNull('deleted_at')
                    ->ignore($controlId),
            ],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['required', Rule::in(['annex_a', 'klausul_4_10'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'framework_id' => 'framework',
            'kode_klausul' => 'kode klausul',
            'judul' => 'judul kontrol',
            'kategori' => 'kategori',
        ];
    }
}
