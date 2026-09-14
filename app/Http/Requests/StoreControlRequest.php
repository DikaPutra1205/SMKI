<?php

namespace App\Http\Requests;

use App\Models\Control;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'framework_id' => ['required', 'integer', 'exists:frameworks,id'],
            'kode_klausul' => [
                'required',
                'string',
                'max:20',
                Rule::unique('controls', 'kode_klausul')
                    ->where('framework_id', $this->input('framework_id'))
                    ->whereNull('deleted_at'),
            ],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['required', Rule::in(Control::KATEGORIS)],
            'domain_peran' => ['nullable', Rule::in(Control::PERANS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'framework_id' => 'framework',
            'kode_klausul' => 'kode klausul',
            'judul' => 'judul kontrol',
            'kategori' => 'kategori',
            'domain_peran' => 'peran domain',
        ];
    }
}
