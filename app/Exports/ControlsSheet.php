<?php

namespace App\Exports;

use App\Models\Control;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ControlsSheet implements Export, FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Controls';
    }

    public function collection(): Enumerable
    {
        return Control::with('framework:id,nama,versi')
            ->orderBy('framework_id')
            ->orderBy('kode_klausul')
            ->get();
    }

    public function headings(): array
    {
        return [
            'framework_nama',    // A — harus cocok persis dengan Sheet Frameworks kolom A
            'framework_versi',   // B — harus cocok persis dengan Sheet Frameworks kolom B
            'kode_klausul',      // C
            'judul',             // D
            'kategori',          // E — annex_a atau klausul_4_10
            'deskripsi',         // F — boleh kosong
        ];
    }

    public function map(mixed $control): array
    {
        return [
            $control->framework?->nama ?? '',
            $control->framework?->versi ?? '',
            $control->kode_klausul,
            $control->judul,
            $control->kategori,
            $control->deskripsi ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row bold + background hijau
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '047857'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }
}
