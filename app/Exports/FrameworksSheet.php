<?php

namespace App\Exports;

use App\Models\Framework;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FrameworksSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Frameworks';
    }

    public function collection()
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

    public function map($framework): array
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

        // Instruksi di atas header (baris info)
        $sheet->insertNewRowBefore(1, 2);
        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', '📋 PANDUAN: Tambah framework baru dengan menambahkan baris baru di bawah. Jangan ubah header. Kolom url_file bersifat opsional.');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '374151']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
            'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(35);

        // Header row (now row 3 after insert)
        $sheet->getStyle('A3:C3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        return [];
    }
}
