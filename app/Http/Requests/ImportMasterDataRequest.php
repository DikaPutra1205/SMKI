<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

                if (! empty($missingSheets) && ! $this->isSingleSheetKontrolFile($filePath, $sheetNames)) {
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

    /**
     * Single-sheet operational export (mis. "Sheet1" berisi daftar kontrol
     * 93 baris gaya TEST SINGLE SHEET.xlsx) diizinkan meski tanpa sheet
     * Frameworks/Controls — framework terdeteksi otomatis dari judul
     * (27001/27701) dan kolom operasional (PIC, Status, Progress, Target,
     * Bukti) sengaja diabaikan agar tidak perlu mengubah skema DB.
     *
     * File yang SUDAH memakai nama sheet resmi (Frameworks/Controls) tapi
     * tidak lengkap tetap ditolak agar kesalahan framework linkage eksplisit.
     */
    private function isSingleSheetKontrolFile(string $filePath, array $sheetNames): bool
    {
        if (count($sheetNames) !== 1) {
            return false;
        }

        if (in_array($sheetNames[0], ['Frameworks', 'Controls'], true)) {
            return false;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = min($sheet->getHighestRow(), 13);
            $highestCol = min(
                Coordinate::columnIndexFromString($sheet->getHighestColumn()),
                12
            );

            for ($r = 1; $r <= $highestRow; $r++) {
                $cells = [];
                for ($c = 1; $c <= $highestCol; $c++) {
                    $cells[] = (string) $sheet->getCell([$c, $r])->getValue();
                }
                $rowText = strtolower(implode(' ', array_filter($cells)));

                $hasKode = str_contains($rowText, 'klausul') || str_contains($rowText, 'kode');
                $hasJudul = str_contains($rowText, 'judul') || str_contains($rowText, 'nama');

                if ($hasKode && $hasJudul) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
