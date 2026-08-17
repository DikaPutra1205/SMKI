<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasFile('file') || ! $this->file('file')->isValid()) {
                return;
            }

            try {
                $filePath = $this->file('file')->getRealPath();
                $reader = IOFactory::createReaderForFile($filePath);
                $sheetNames = $reader->listWorksheetNames($filePath);

                $requiredSheets = ['Frameworks', 'Controls'];
                $missingSheets = array_diff($requiredSheets, $sheetNames);

                if (! empty($missingSheets)) {
                    $validator->errors()->add(
                        'file',
                        'File Excel harus memiliki sheet: '.implode(' dan ', $missingSheets).'.'
                    );
                }
            } catch (\Throwable $e) {
                $validator->errors()->add('file', 'File Excel tidak dapat dibaca atau rusak.');
            }
        });
    }
}
