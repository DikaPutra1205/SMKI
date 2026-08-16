<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmkiMasterDataExport implements WithMultipleSheets
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
