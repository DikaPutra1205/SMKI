<?php

namespace App\Http\Requests;

use App\Models\Control;
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
        $control = $this->route('control');
        $controlId = $control instanceof Control ? $control->id : $control;
        $existingControl = $control instanceof Control ? $control : ($controlId ? Control::find($controlId) : null);
        $frameworkId = $this->input('framework_id') ?? $existingControl?->framework_id;

        return [
            'framework_id' => ['sometimes', 'required', 'integer', 'exists:frameworks,id'],
            'kode_klausul' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('controls', 'kode_klausul')
                    ->where(fn ($query) => $frameworkId ? $query->where('framework_id', $frameworkId) : $query)
                    ->whereNull('deleted_at')
                    ->ignore($controlId),
            ],
            'judul' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['sometimes', 'required', Rule::in(['annex_a', 'klausul_4_10'])],
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
