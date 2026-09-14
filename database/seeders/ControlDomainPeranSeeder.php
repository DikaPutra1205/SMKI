<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ControlDomainPeranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // For ISO 27701 (framework_id = 2)
        // Clause 7.x are for PII Controllers
        // Clause 8.x are for PII Processors
        // Clause 5.x and 6.x don't have a specific role, they are shared/general

        $controls = DB::table('controls')->where('framework_id', 2)->get();

        foreach ($controls as $control) {
            $peran = null;
            if (str_starts_with($control->kode_klausul, '7.')) {
                $peran = 'controller';
            } elseif (str_starts_with($control->kode_klausul, '8.')) {
                $peran = 'processor';
            }

            DB::table('controls')
                ->where('id', $control->id)
                ->update(['domain_peran' => $peran]);
        }
    }
}
