<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmkiMasterDataExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Frameworks' => new FrameworksSheet,
            'Controls' => new ControlsSheet,
        ];
    }
}
