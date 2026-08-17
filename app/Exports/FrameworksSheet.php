<?php

namespace App\Exports;

use App\Models\Framework;
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

class FrameworksSheet implements Export, FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Frameworks';
    }

    public function collection(): Enumerable
    {
        return Framework::orderBy('id')->get();
    }

    public function headings(): array
    {
        return [
            'nama',          // A — nama framework
            'versi',         // B — versi (e.g. 2022)
            'url_file',      // C — URL dokumen standar (opsional)
        ];
    }

    public function map(mixed $framework): array
    {
        return [
            $framework->nama,
            $framework->versi,
            $framework->url_file ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row bold + background biru
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }
}
