<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $control = $this->route('control');

        return [
            'framework_id' => ['sometimes', 'integer', 'exists:frameworks,id'],
            'kode_klausul' => [
                'required',
                'string',
                'max:20',
                Rule::unique('controls', 'kode_klausul')
                    ->where('framework_id', $this->input('framework_id', $control?->framework_id))
                    ->ignore($control?->id),
            ],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['required', 'in:annex_a,klausul_4_10'],
        ];
    }
}
