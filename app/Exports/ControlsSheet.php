<?php

namespace App\Exports;

use App\Models\Control;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ControlsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Controls';
    }

    public function collection()
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

    public function map($control): array
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
        // Instruksi di atas header
        $sheet->insertNewRowBefore(1, 2);
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue(
            'A1',
            '📋 PANDUAN: Tambah kontrol baru dengan menambahkan baris baru di bawah. '.
            'Kolom framework_nama & framework_versi harus cocok persis dengan Sheet "Frameworks". '.
            'Kategori: annex_a atau klausul_4_10.',
        );
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '374151']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
            'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(45);

        // Header row (now row 3)
        $sheet->getStyle('A3:F3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        return [];
    }
}
